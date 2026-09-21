<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * Service <-> instance mapping and the activity log.
 *
 * States: new, creating (write-ahead before POST), unknown (POST result unclear),
 * provisioning, active, failed, terminated.
 */
class Repo
{
    public static function find($serviceId)
    {
        Schema::ensure();
        return Capsule::table(Schema::SERVICES)->where('service_id', (int) $serviceId)->first();
    }

    public static function findByInstance($instanceId)
    {
        Schema::ensure();
        return Capsule::table(Schema::SERVICES)
            ->where('instance_id', (string) $instanceId)
            ->where('state', '!=', 'terminated')
            ->first();
    }

    public static function update($serviceId, array $data)
    {
        $data['updated_at'] = self::now();
        return Capsule::table(Schema::SERVICES)->where('service_id', (int) $serviceId)->update($data);
    }

    /**
     * Write-ahead claim before POST /instances. Only one caller can move a service
     * into "creating"; a second accept or a gateway callback racing it gets false.
     */
    public static function claimCreating($serviceId, array $data)
    {
        Schema::ensure();
        $now = self::now();
        $data = array_merge($data, [
            'state' => 'creating',
            'instance_id' => null,
            'error' => null,
            'snapshot' => null,
            'creating_at' => $now,
            'ready_at' => null,
            'ready_notified_at' => null,
            'alerted_at' => null,
            'updated_at' => $now,
        ]);
        try {
            Capsule::table(Schema::SERVICES)->insert(array_merge($data, [
                'service_id' => (int) $serviceId,
                'created_at' => $now,
            ]));
            return true;
        } catch (\Throwable $e) {
            // Row exists: only restart from a state that has nothing upstream.
            return Capsule::table(Schema::SERVICES)
                ->where('service_id', (int) $serviceId)
                ->whereIn('state', ['new', 'failed', 'terminated'])
                ->update($data) === 1;
        }
    }

    /** Link an existing instance (admin "link" or adoption). */
    public static function link($serviceId, array $data)
    {
        Schema::ensure();
        $now = self::now();
        $data['updated_at'] = $now;
        if (self::find($serviceId)) {
            return Capsule::table(Schema::SERVICES)->where('service_id', (int) $serviceId)->update($data);
        }
        return Capsule::table(Schema::SERVICES)->insert(array_merge($data, [
            'service_id' => (int) $serviceId,
            'created_at' => $now,
        ]));
    }

    public static function snapshot($row)
    {
        if (!$row || empty($row->snapshot)) {
            return [];
        }
        $data = json_decode($row->snapshot, true);
        return is_array($data) ? $data : [];
    }

    public static function log($serviceId, $action, $ok, $message = '', $amountCents = null, $instanceId = null, $actor = null)
    {
        try {
            Schema::ensure();
            Capsule::table(Schema::LOG)->insert([
                'service_id' => $serviceId ? (int) $serviceId : null,
                'instance_id' => $instanceId,
                'actor' => $actor ?: self::actor(),
                'action' => substr((string) $action, 0, 64),
                'ok' => $ok ? 1 : 0,
                'message' => $message === '' ? null : (string) $message,
                'amount_cents' => $amountCents === null ? null : (int) $amountCents,
                'created_at' => self::now(),
            ]);
        } catch (\Throwable $e) {
            if (function_exists('logActivity')) {
                logActivity('Evorxa: could not write module log: ' . $e->getMessage());
            }
        }
    }

    public static function logs($serviceId, $limit = 10)
    {
        Schema::ensure();
        return Capsule::table(Schema::LOG)
            ->where('service_id', (int) $serviceId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public static function lastLog($serviceId, $action, $okOnly = true)
    {
        $q = Capsule::table(Schema::LOG)->where('service_id', (int) $serviceId)->where('action', $action);
        if ($okOnly) {
            $q->where('ok', 1);
        }
        return $q->orderBy('id', 'desc')->first();
    }

    public static function actor()
    {
        if (defined('CLIENTAREA')) {
            return 'client';
        }
        if (!empty($_SESSION['adminid'])) {
            return 'admin:' . (int) $_SESSION['adminid'];
        }
        if (PHP_SAPI === 'cli') {
            return 'cron';
        }
        return 'system';
    }

    public static function now()
    {
        return date('Y-m-d H:i:s');
    }

    public static function hosting($serviceId)
    {
        return Capsule::table('tblhosting')->where('id', (int) $serviceId)->first();
    }
}
