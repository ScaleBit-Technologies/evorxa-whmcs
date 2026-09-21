<?php

namespace WHMCS\Module\Server\Evorxa\Admin;

use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;
use WHMCS\Module\Server\Evorxa\Catalog;
use WHMCS\Module\Server\Evorxa\Keeper;
use WHMCS\Module\Server\Evorxa\Provisioner;
use WHMCS\Module\Server\Evorxa\Repo;
use WHMCS\Module\Server\Evorxa\Util;
use WHMCS\Module\Server\Evorxa\View;
use WHMCS\Module\Server\Evorxa\Watcher;

/**
 * "Evorxa" block on the admin service page (Clients > Products/Services).
 */
class ServiceTab
{
    const FIELD = 'evorxa_link_instance';

    public static function fields(array $params)
    {
        $sid = (int) $params['serviceid'];
        $row = Repo::find($sid);
        $snap = Repo::snapshot($row);
        $live = null;
        $liveError = '';
        if ($row && $row->instance_id && !in_array($row->state, ['terminated'], true)) {
            try {
                $live = Provisioner::unwrap(Client::fromParams($params)->get('instances/' . rawurlencode($row->instance_id)));
                if (!Provisioner::fenceOk($row, $live)) {
                    $liveError = 'Server is not in the expected Evorxa project #' . (int) $row->project_id . '; the module will not touch it.';
                } else {
                    $snap = Provisioner::snapshotOf($live);
                    Repo::update($sid, ['snapshot' => json_encode($snap), 'last_sync_at' => Repo::now()]);
                }
            } catch (ApiException $e) {
                $liveError = $e->friendly();
            }
        }

        $plan = null;
        if ($row && $row->package_id) {
            try {
                $plan = (new Catalog())->plan($row->package_id);
            } catch (\Throwable $e) {
            }
        }

        $logs = [];
        foreach (Repo::logs($sid, 8) as $log) {
            $logs[] = [
                'when' => $log->created_at,
                'actor' => $log->actor,
                'action' => $log->action,
                'ok' => (bool) $log->ok,
                'message' => (string) $log->message,
                'amount' => $log->amount_cents === null ? '' : Util::formatCents($log->amount_cents),
            ];
        }

        $vars = [
            'field' => self::FIELD,
            'linked' => (bool) ($row && $row->instance_id),
            'row' => $row ? [
                'state' => $row->state,
                'instance' => $row->instance_id,
                'name' => $row->name,
                'project' => $row->project_id,
                'cycle' => $row->upstream_cycle,
                'app' => $row->app_slug,
                'os' => $row->os_label,
                'error' => $row->error,
                'lastSync' => $row->last_sync_at,
                'readyAt' => $row->ready_at,
                'notified' => $row->ready_notified_at,
            ] : null,
            'plan' => $plan ? Catalog::planLabel($plan) : ($row && $row->package_id ? '#' . $row->package_id : ''),
            'inst' => $snap ? [
                'status' => isset($snap['status']) ? $snap['status'] : '',
                'ip' => isset($snap['main_ip']) ? $snap['main_ip'] : '',
                'hostname' => isset($snap['hostname']) ? $snap['hostname'] : '',
                'billedTill' => Util::date(isset($snap['billed_till']) ? $snap['billed_till'] : null, true),
                'billingStatus' => isset($snap['billing_status']) ? $snap['billing_status'] : '',
                'deletion' => Util::date(isset($snap['scheduled_deletion_at']) ? $snap['scheduled_deletion_at'] : null, true),
                'suspended' => Keeper::upstreamSuspended($snap),
                'location' => isset($snap['location']) ? $snap['location'] : '',
                'plan' => isset($snap['plan']) ? $snap['plan'] : '',
            ] : null,
            'liveError' => $liveError,
            'cancelPending' => $row ? Keeper::pendingEndOfPeriodCancel($sid) : false,
            'logs' => $logs,
            'managerUrl' => 'addonmodules.php?module=evorxa_manager&page=log&service=' . $sid,
        ];
        return ['Evorxa' => View::render('admin/servicetab.tpl', $vars)];
    }

    /**
     * Link an existing Evorxa server to this service (or "unlink").
     * The server must be in a project the token owns and must not be linked elsewhere.
     */
    public static function save(array $params)
    {
        $value = trim(isset($_POST[self::FIELD]) ? (string) $_POST[self::FIELD] : '');
        if ($value === '') {
            return;
        }
        $sid = (int) $params['serviceid'];
        $row = Repo::find($sid);
        if ($row && (string) $row->instance_id === $value) {
            return;
        }
        if (strtolower($value) === 'unlink') {
            if ($row) {
                Repo::update($sid, ['state' => 'new', 'instance_id' => null, 'snapshot' => null]);
                Repo::log($sid, 'unlink', true, 'Unlinked from ' . $row->instance_id . ' (server left untouched upstream)', null, $row->instance_id);
            }
            return;
        }
        if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $value)) {
            Repo::log($sid, 'link', false, 'Not a valid Evorxa server id: ' . $value);
            return;
        }
        $api = Client::fromParams($params);
        $inst = Provisioner::unwrap($api->get('instances/' . rawurlencode($value)));
        $owned = (new Catalog($api))->ownedProjects();
        $project = isset($inst['project_id']) ? (int) $inst['project_id'] : 0;
        if (!isset($owned[$project])) {
            Repo::log($sid, 'link', false, 'Server ' . $value . ' is not in a project you own; not linked.');
            return;
        }
        $other = Repo::findByInstance($value);
        if ($other && (int) $other->service_id !== $sid) {
            Repo::log($sid, 'link', false, 'Server ' . $value . ' is already linked to service #' . $other->service_id . '.');
            return;
        }
        $plan = !empty($inst['plan']) ? (new Catalog($api))->planBySlug($inst['plan']) : null;
        $building = Provisioner::isBuilding($inst);
        Repo::link($sid, [
            'instance_id' => (string) $inst['id'],
            'project_id' => $project,
            'package_id' => $plan ? (int) $plan['package_id'] : null,
            'upstream_cycle' => isset($inst['billing_cycle']) ? (string) $inst['billing_cycle'] : null,
            'name' => isset($inst['name']) ? Util::clean($inst['name'], 100) : null,
            'state' => $building ? 'provisioning' : 'active',
            'os_label' => isset($inst['os']) ? Util::clean($inst['os'], 190) : null,
            'app_slug' => isset($inst['current_app']['slug']) ? $inst['current_app']['slug'] : null,
            'snapshot' => json_encode(Provisioner::snapshotOf($inst)),
            'last_sync_at' => Repo::now(),
            'ready_at' => $building ? null : Repo::now(),
            // Linking an existing server never emails the client.
            'ready_notified_at' => Repo::now(),
            'error' => null,
        ]);
        if (!$building) {
            Watcher::writeCredentials($sid, $inst);
        }
        Repo::log($sid, 'link', true, 'Linked to existing server ' . $inst['id'] . ' (' . (isset($inst['hostname']) ? $inst['hostname'] : '') . ')', null, (string) $inst['id']);
    }
}
