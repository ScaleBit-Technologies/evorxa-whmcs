<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * Keeps the upstream billing of one server in line with what the client has paid.
 *
 * Evorxa renews every instance from the wallet 5 days before billed_till, so in the
 * normal case nothing is paid from here. The keeper only repairs:
 *  - suspended upstream while the client is paid up   -> reactivate
 *  - end-of-period cancellation requested in WHMCS     -> stop the upstream renewal in time
 *  - deletion scheduled but the client is staying      -> cancel the deletion
 *  - account auto-renew switched off                   -> extend, once per billed_till value
 */
class Keeper
{
    public static function run(Client $api, $row, $hosting, array $inst, $autoRenew)
    {
        if (!$row || !$row->instance_id || !$hosting || $hosting->domainstatus !== 'Active' || !empty($inst['deleted_at'])) {
            return [];
        }
        $sid = (int) $row->service_id;
        $id = rawurlencode($row->instance_id);
        $done = [];

        if (self::upstreamSuspended($inst)) {
            if (!Cache::acquire('reactivate:' . $sid, 3600)) {
                return $done;
            }
            try {
                $res = $api->post('instances/' . $id . '/reactivate');
                Repo::log($sid, 'reactivate', true, 'Reactivated after upstream suspension (client is paid up)', self::charged($res), $row->instance_id);
                $done[] = 'reactivate';
            } catch (ApiException $e) {
                Repo::log($sid, 'reactivate', false, $e->friendly(), null, $row->instance_id);
                Mailer::alertAdmin(
                    'reactivate-' . $sid,
                    'Server for service #' . $sid . ' is suspended upstream',
                    'The client has paid, but Evorxa suspended the server (usually a short wallet at renewal). '
                    . 'Automatic reactivation failed: ' . $e->friendly() . ' It is retried every hour.'
                );
            }
            return $done;
        }

        $billedTill = Util::ts(isset($inst['billed_till']) ? $inst['billed_till'] : null);
        $due = self::dueTs($hosting);

        if (self::pendingEndOfPeriodCancel($sid)) {
            // Stop renewing once the current upstream period already covers the client's paid time.
            if (empty($inst['scheduled_deletion_at']) && $billedTill && $due && $billedTill >= $due - 3 * 86400) {
                try {
                    $api->delete('instances/' . $id, ['mode' => 'end_of_cycle']);
                    Repo::log($sid, 'schedule_deletion', true, 'End-of-period cancellation: upstream renewal stopped', null, $row->instance_id);
                    $done[] = 'schedule_deletion';
                } catch (ApiException $e) {
                    Repo::log($sid, 'schedule_deletion', false, $e->friendly(), null, $row->instance_id);
                }
            }
            return $done;
        }

        if (!empty($inst['scheduled_deletion_at'])) {
            try {
                $api->post('instances/' . $id . '/cancel-deletion');
                Repo::log($sid, 'cancel_deletion', true, 'Scheduled upstream deletion cancelled (service is active)', null, $row->instance_id);
                $done[] = 'cancel_deletion';
            } catch (ApiException $e) {
                Repo::log($sid, 'cancel_deletion', false, $e->friendly(), null, $row->instance_id);
            }
        }

        if ($autoRenew) {
            return $done;
        }

        $cycle = $row->upstream_cycle && isset(Util::CYCLE_DAYS[$row->upstream_cycle]) ? $row->upstream_cycle : 'monthly';
        $half = (int) (Util::CYCLE_DAYS[$cycle] * 86400 / 2);
        $billedRaw = isset($inst['billed_till']) ? (string) $inst['billed_till'] : '';
        if ($billedTill && $due && $billedTill < $due - $half && (string) $row->last_extend_billed_till !== $billedRaw) {
            // Record first: even if the response is lost we never extend twice for the same period.
            Repo::update($sid, ['last_extend_billed_till' => $billedRaw]);
            try {
                $res = $api->post('instances/' . $id . '/extend');
                Repo::log($sid, 'extend', true, 'Extended one ' . $cycle . ' cycle (account auto-renew is off)', self::charged($res), $row->instance_id);
                $done[] = 'extend';
            } catch (ApiException $e) {
                Repo::log($sid, 'extend', false, $e->friendly(), null, $row->instance_id);
                Mailer::alertAdmin('extend-' . $sid, 'Could not extend server for service #' . $sid, $e->friendly());
            }
        }
        return $done;
    }

    /** Run for a single service (renewal paid, unsuspend, cancellation request). */
    public static function forService($serviceId, Client $api = null)
    {
        $row = Repo::find($serviceId);
        if (!$row || !$row->instance_id || in_array($row->state, ['terminated', 'failed'], true)) {
            return [];
        }
        $hosting = Repo::hosting($serviceId);
        if (!$hosting) {
            return [];
        }
        $api = $api ?: Watcher::clientFor($hosting);
        $inst = Provisioner::unwrap($api->get('instances/' . rawurlencode($row->instance_id)));
        if (!Provisioner::fenceOk($row, $inst)) {
            Repo::log($serviceId, 'keeper', false, 'Instance is not in the expected project; skipped', null, $row->instance_id);
            return [];
        }
        $me = (new Catalog($api))->me();
        $autoRenew = !isset($me['user']['auto_renew']) || !empty($me['user']['auto_renew']);
        return self::run($api, $row, $hosting, $inst, $autoRenew);
    }

    public static function upstreamSuspended(array $inst)
    {
        return !empty($inst['suspended_at'])
            || (isset($inst['status']) && strtolower($inst['status']) === 'suspended')
            || (isset($inst['billing_status']) && strtolower($inst['billing_status']) === 'suspended');
    }

    public static function pendingEndOfPeriodCancel($serviceId)
    {
        return Capsule::table('tblcancelrequests')
            ->where('relid', (int) $serviceId)
            ->where('type', 'End of Billing Period')
            ->count() > 0;
    }

    private static function dueTs($hosting)
    {
        $due = (string) $hosting->nextduedate;
        if ($due === '' || strpos($due, '0000') === 0) {
            return null;
        }
        $ts = strtotime($due . ' 00:00:00 UTC');
        return $ts ?: null;
    }

    /** Charged cents from an upstream response, when it says so. */
    public static function charged($res)
    {
        if (!is_array($res)) {
            return null;
        }
        foreach (['charged_cents', 'amount_cents', 'cost_cents', 'debited_cents', 'paid_cents'] as $key) {
            if (isset($res[$key]) && is_numeric($res[$key])) {
                return (int) $res[$key];
            }
        }
        return null;
    }
}
