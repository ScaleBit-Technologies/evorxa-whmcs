<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * Background work, run from the WHMCS cron (AfterCronJob) under a MySQL lock:
 *  - adopt servers whose create call timed out
 *  - finish provisioning (credentials into WHMCS, ready email)
 *  - hourly: sync every linked server and run the renewal keeper, sync plan stock
 *  - daily: wallet balance check
 */
class Watcher
{
    const LOCK = 'evorxa_watcher';
    const BUDGET_SECONDS = 45;

    private static $clients = [];

    public static function tick()
    {
        Schema::ensure();
        if (!Client::defaultServer() || !Cache::lock(self::LOCK, 0)) {
            return;
        }
        $deadline = time() + self::BUDGET_SECONDS;
        try {
            self::resolveUnknown($deadline);
            self::pollProvisioning($deadline);
            if (time() < $deadline && Cache::acquire('gate:sync', 3300)) {
                self::syncAll($deadline);
            }
            if (time() < $deadline && Cache::acquire('gate:stock', 3300)) {
                self::syncStock();
            }
            if (time() < $deadline && Cache::acquire('gate:balance', 86000)) {
                self::checkBalance();
            }
        } catch (\Throwable $e) {
            if (function_exists('logActivity')) {
                logActivity('Evorxa cron error: ' . $e->getMessage());
            }
        } finally {
            Cache::unlock(self::LOCK);
        }
    }

    /** API client for the server a service is assigned to (falls back to the default server). */
    public static function clientFor($hosting)
    {
        $serverId = $hosting && $hosting->server ? (int) $hosting->server : 0;
        if (!isset(self::$clients[$serverId])) {
            self::$clients[$serverId] = $serverId ? Client::fromServerId($serverId) : Client::forDefaultServer();
        }
        return self::$clients[$serverId];
    }

    // ------------------------------------------------------------------ adoption

    private static function resolveUnknown($deadline)
    {
        $stale = date('Y-m-d H:i:s', time() - 300);
        $rows = Capsule::table(Schema::SERVICES)
            ->where(function ($q) use ($stale) {
                $q->where('state', 'unknown')
                    ->orWhere(function ($q2) use ($stale) {
                        $q2->where('state', 'creating')->where('creating_at', '<', $stale);
                    });
            })
            ->limit(10)
            ->get();
        foreach ($rows as $row) {
            if (time() >= $deadline) {
                return;
            }
            try {
                self::adopt(self::clientFor(Repo::hosting($row->service_id)), $row, true);
            } catch (\Throwable $e) {
                Repo::log($row->service_id, 'adopt', false, $e->getMessage(), null, null, 'cron');
            }
        }
    }

    /**
     * Find the server a timed-out create may have built (exact name inside the project).
     * With $giveUp, a create older than 15 minutes with no server is marked failed so it can be retried.
     */
    public static function adopt(Client $api, $row, $giveUp)
    {
        if (!$row->name || !$row->project_id) {
            return false;
        }
        $list = $api->get('instances', ['project_id' => (int) $row->project_id]);
        foreach ((array) $list as $inst) {
            if (!is_array($inst) || (isset($inst['name']) ? $inst['name'] : null) !== $row->name || !empty($inst['deleted_at'])) {
                continue;
            }
            if ((int) (isset($inst['project_id']) ? $inst['project_id'] : 0) !== (int) $row->project_id) {
                continue;
            }
            $other = Repo::findByInstance($inst['id']);
            if ($other && (int) $other->service_id !== (int) $row->service_id) {
                continue;
            }
            Repo::update($row->service_id, [
                'state' => 'provisioning',
                'instance_id' => (string) $inst['id'],
                'snapshot' => json_encode(Provisioner::snapshotOf($inst)),
                'last_sync_at' => Repo::now(),
                'error' => null,
            ]);
            Repo::log($row->service_id, 'adopt', true, 'Found the server created by the unconfirmed order', null, (string) $inst['id']);
            $hosting = Repo::hosting($row->service_id);
            if ($hosting && $hosting->domainstatus === 'Pending') {
                // Let WHMCS record the create as successful (our create() returns success for this case).
                localAPI('ModuleCreate', ['serviceid' => (int) $row->service_id]);
            }
            return true;
        }
        $started = strtotime((string) $row->creating_at);
        if ($giveUp && $started && time() - $started > 900) {
            Repo::update($row->service_id, ['state' => 'failed', 'error' => 'Evorxa never created the server; safe to retry Create.']);
            Repo::log($row->service_id, 'adopt', false, 'No server was created upstream; marked failed (safe to retry Create)');
            Provisioner::deleteSshKeyWith($api, $row->ssh_key_id);
            Mailer::alertAdmin(
                'create-failed-' . $row->service_id,
                'Server for service #' . $row->service_id . ' was not created',
                'Evorxa never confirmed or built the server. Retry "Create" from the service page (or the Module Queue).'
            );
        }
        return false;
    }

    // ------------------------------------------------------------------ provisioning

    private static function pollProvisioning($deadline)
    {
        $rows = Capsule::table(Schema::SERVICES)->where('state', 'provisioning')->orderBy('updated_at')->limit(15)->get();
        foreach ($rows as $row) {
            if (time() >= $deadline) {
                return;
            }
            try {
                self::finalize(self::clientFor(Repo::hosting($row->service_id)), $row);
            } catch (\Throwable $e) {
                Repo::log($row->service_id, 'finalize', false, $e->getMessage(), null, $row->instance_id, 'cron');
            }
        }
    }

    /**
     * Move a provisioning server to active once it has booted (and its app is ready):
     * store credentials on the WHMCS service and send the ready email once.
     * Returns true when the server is ready.
     */
    public static function finalize(Client $api, $row)
    {
        $inst = Provisioner::unwrap($api->get('instances/' . rawurlencode($row->instance_id)));
        if (!Provisioner::fenceOk($row, $inst)) {
            Repo::log($row->service_id, 'finalize', false, 'Instance is not in the expected project; not touching it', null, $row->instance_id);
            return false;
        }
        Repo::update($row->service_id, ['snapshot' => json_encode(Provisioner::snapshotOf($inst)), 'last_sync_at' => Repo::now()]);

        $status = strtolower(isset($inst['status']) ? (string) $inst['status'] : '');
        $timedOut = self::timedOut($row);

        if (in_array($status, ['error', 'failed'], true)) {
            Repo::update($row->service_id, ['state' => 'failed', 'error' => 'Evorxa reported the build as ' . $status]);
            Repo::log($row->service_id, 'provision', false, 'Evorxa reported the build as ' . $status, null, $row->instance_id);
            Mailer::alertAdmin('build-failed-' . $row->service_id, 'Server build failed for service #' . $row->service_id, 'Evorxa reported status "' . $status . '" for server ' . $row->instance_id . '.');
            return false;
        }

        if (Provisioner::isBuilding($inst)) {
            self::maybeAlertSlow($row, $timedOut);
            return false;
        }

        $app = null;
        if ($row->app_slug) {
            try {
                $res = $api->get('instances/' . rawurlencode($row->instance_id) . '/app');
                $app = isset($res['app']) && is_array($res['app']) ? $res['app'] : null;
            } catch (ApiException $e) {
                $app = null;
            }
            if ($app && isset($app['status']) && $app['status'] !== 'ready' && !$timedOut) {
                return false; // Application still installing.
            }
            if (!$app) {
                // Evorxa answers null when the server has no application (e.g. rebuilt with a plain OS elsewhere).
                Repo::update($row->service_id, ['app_slug' => null, 'os_label' => Util::clean(isset($inst['os']) ? $inst['os'] : '', 190)]);
                $row->os_label = isset($inst['os']) ? $inst['os'] : $row->os_label;
            }
            if ($app) {
                $catalogApps = (new Catalog())->apps();
                if (isset($catalogApps[$row->app_slug]['setup_note'])) {
                    $app['setup_note'] = $catalogApps[$row->app_slug]['setup_note'];
                }
            }
        }

        self::writeCredentials($row->service_id, $inst);
        Repo::update($row->service_id, ['state' => 'active', 'ready_at' => Repo::now(), 'error' => null]);
        Provisioner::deleteSshKeyWith($api, $row->ssh_key_id);
        if ($row->ssh_key_id) {
            Repo::update($row->service_id, ['ssh_key_id' => null]);
        }
        if (!$row->ready_notified_at) {
            Repo::log($row->service_id, 'provision', true, 'Server is ready', null, $row->instance_id);
        }
        Cache::delete('inst:' . $row->instance_id);
        Mailer::sendReady($row->service_id, Mailer::readyVars($row->service_id, $inst, $app, $row->os_label));
        return true;
    }

    /** Hostname, IPs, username and the (encrypted) root password onto the WHMCS service. */
    public static function writeCredentials($serviceId, array $inst)
    {
        $update = [];
        if (!empty($inst['hostname'])) {
            $update['domain'] = Util::clean($inst['hostname'], 190);
        }
        if (!empty($inst['main_ip'])) {
            $update['dedicatedip'] = (string) $inst['main_ip'];
        }
        $others = [];
        foreach ((array) (isset($inst['ip_addresses']) ? $inst['ip_addresses'] : []) as $ip) {
            if (!empty($ip['address']) && $ip['address'] !== (isset($inst['main_ip']) ? $inst['main_ip'] : null)) {
                $others[] = $ip['address'];
            }
        }
        $update['assignedips'] = implode("\n", $others);
        if (!empty($inst['username'])) {
            $update['username'] = Util::clean($inst['username'], 60);
        }
        if (!empty($inst['password']) && is_string($inst['password'])) {
            $update['password'] = encrypt($inst['password']);
        }
        Capsule::table('tblhosting')->where('id', (int) $serviceId)->update($update);
    }

    private static function timedOut($row)
    {
        $minutes = max(10, (int) Settings::get('provision_timeout'));
        $started = strtotime((string) ($row->creating_at ?: $row->updated_at));
        return $started && time() - $started > $minutes * 60;
    }

    private static function maybeAlertSlow($row, $timedOut)
    {
        if (!$timedOut || $row->alerted_at) {
            return;
        }
        Repo::update($row->service_id, ['alerted_at' => Repo::now()]);
        Repo::log($row->service_id, 'provision', false, 'Server is still building after the provisioning timeout', null, $row->instance_id);
        Mailer::alertAdmin(
            'slow-' . $row->service_id,
            'Server for service #' . $row->service_id . ' is taking long to build',
            'Server ' . $row->instance_id . ' is still being built. The module keeps checking and emails the client when it is ready.'
        );
    }

    // ------------------------------------------------------------------ hourly sync + keeper

    private static function syncAll($deadline)
    {
        $rows = Capsule::table(Schema::SERVICES)->whereIn('state', ['active', 'provisioning'])->whereNotNull('instance_id')->get();
        $groups = [];
        foreach ($rows as $row) {
            $hosting = Repo::hosting($row->service_id);
            $serverKey = $hosting && $hosting->server ? (int) $hosting->server : 0;
            $groups[$serverKey . ':' . (int) $row->project_id][] = [$row, $hosting];
        }
        foreach ($groups as $key => $items) {
            if (time() >= $deadline) {
                return;
            }
            list(, $projectId) = explode(':', $key);
            try {
                $api = self::clientFor($items[0][1]);
                $list = $api->get('instances', ['project_id' => (int) $projectId]);
                $me = (new Catalog($api))->me();
            } catch (\Throwable $e) {
                continue;
            }
            $autoRenew = !isset($me['user']['auto_renew']) || !empty($me['user']['auto_renew']);
            $byId = [];
            foreach ((array) $list as $inst) {
                if (is_array($inst) && isset($inst['id'])) {
                    $byId[(string) $inst['id']] = $inst;
                }
            }
            foreach ($items as $item) {
                list($row, $hosting) = $item;
                $inst = isset($byId[$row->instance_id]) ? $byId[$row->instance_id] : null;
                if (!$inst) {
                    self::flagMissing($row, $hosting);
                    continue;
                }
                $snapshot = Provisioner::snapshotOf($inst);
                Repo::update($row->service_id, ['snapshot' => json_encode($snapshot), 'last_sync_at' => Repo::now()]);
                if ($row->state !== 'active') {
                    continue;
                }
                try {
                    Keeper::run($api, $row, $hosting, $inst, $autoRenew);
                } catch (\Throwable $e) {
                    Repo::log($row->service_id, 'keeper', false, $e->getMessage(), null, $row->instance_id, 'cron');
                }
            }
        }
    }

    private static function flagMissing($row, $hosting)
    {
        if (!$hosting || !in_array($hosting->domainstatus, ['Active', 'Suspended'], true)) {
            return;
        }
        Repo::log($row->service_id, 'sync', false, 'Linked server not found in its Evorxa project (deleted upstream?)', null, $row->instance_id, 'cron');
        Mailer::alertAdmin(
            'missing-' . $row->service_id,
            'Server for service #' . $row->service_id . ' is missing upstream',
            'Service #' . $row->service_id . ' is ' . $hosting->domainstatus . ' in WHMCS but its server ' . $row->instance_id
            . ' is not in Evorxa project #' . (int) $row->project_id . '. Check Addons > Evorxa Manager > Servers.',
            86400
        );
    }

    // ------------------------------------------------------------------ stock + wallet

    public static function syncStock()
    {
        $products = Capsule::table('tblproducts')->where('servertype', 'evorxa')->where('stockcontrol', 1)->get(['id', 'configoption1', 'qty']);
        if (!count($products)) {
            return 0;
        }
        $catalog = new Catalog(Client::forDefaultServer());
        $plans = $catalog->plans(true);
        $changed = 0;
        foreach ($products as $product) {
            $pkg = (int) $product->configoption1;
            if (!isset($plans[$pkg]['stock'])) {
                continue;
            }
            $stock = max(0, (int) $plans[$pkg]['stock']);
            if ((int) $product->qty !== $stock) {
                Capsule::table('tblproducts')->where('id', $product->id)->update(['qty' => $stock]);
                $changed++;
            }
        }
        return $changed;
    }

    public static function checkBalance()
    {
        $api = Client::forDefaultServer();
        $balance = $api->get('wallet/balance');
        $cents = isset($balance['balance_cents']) ? (int) $balance['balance_cents'] : 0;
        $threshold = (int) round(((float) Settings::get('low_balance')) * 100);
        $me = (new Catalog($api))->me(true);
        $renewal = isset($me['user']['next_renewal']) && is_array($me['user']['next_renewal']) ? $me['user']['next_renewal'] : null;
        $upcoming = 0;
        if ($renewal && !empty($renewal['at']) && Util::ts($renewal['at']) < time() + 7 * 86400) {
            $upcoming = (int) (isset($renewal['amount_cents']) ? $renewal['amount_cents'] : 0);
        }
        if (($threshold > 0 && $cents < $threshold) || ($upcoming > 0 && $cents < $upcoming)) {
            $msg = 'Evorxa wallet balance is ' . Util::formatCents($cents) . '.';
            if ($upcoming > 0) {
                $msg .= ' Renewals of ' . Util::formatCents($upcoming) . ' are due on ' . Util::date($renewal['at']) . '.';
            }
            $msg .= ' Servers are suspended upstream when a renewal cannot be paid, and new orders cannot be provisioned. Top up the wallet in the Evorxa dashboard.';
            Mailer::alertAdmin('low-balance', 'Evorxa wallet balance is low', $msg, 86000);
        }
        return $cents;
    }
}
