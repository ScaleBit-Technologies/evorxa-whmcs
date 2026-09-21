<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * JSON endpoint for the client panel.
 *
 * WHMCS has already checked that the logged-in client owns the service before calling
 * evorxa_api(). On top of that every request must be a POST with the CSRF token, the
 * action must be whitelisted and enabled, and every upstream id (instance, Shield IP,
 * firewall rule) is resolved server-side from the service - never taken from the request.
 */
class ClientActions
{
    const ACTIONS = [
        'status' => null,
        'power' => 'power',
        'password_reveal' => null,
        'password_reset' => 'password',
        'app_reveal' => null,
        'reinstall_options' => 'reinstall',
        'reinstall' => 'reinstall',
        'metrics' => 'graphs',
        'ddos' => 'ddos',
        'ddos_save' => 'ddos',
        'firewall' => 'firewall',
        'firewall_add' => 'firewall',
        'firewall_delete' => 'firewall',
        'firewall_policy' => 'firewall',
    ];

    const METRIC_PERIODS = ['1h', '6h', '24h'];
    const DDOS_PERIODS = ['live', '1h', '1d', '1w'];

    private $params;
    private $sid;
    private $row;
    private $snap;
    private $api;

    public static function respond(array $params)
    {
        try {
            $out = (new self($params))->handle();
        } catch (\Throwable $e) {
            logModuleCall('evorxa', 'client api exception', '', $e->getMessage() . "\n" . $e->getTraceAsString());
            $out = ['ok' => false, 'error' => Lang::t('err_generic')];
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, private');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode($out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function __construct(array $params)
    {
        $this->params = $params;
        $this->sid = (int) $params['serviceid'];
    }

    private function handle()
    {
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '') !== 'POST') {
            return $this->fail('err_session');
        }
        $token = isset($_POST['token']) ? (string) $_POST['token'] : '';
        if ($token === '' || !function_exists('generate_token') || !hash_equals((string) generate_token('plain'), $token)) {
            return $this->fail('err_session');
        }
        $action = isset($_POST['do']) ? (string) $_POST['do'] : '';
        if (!array_key_exists($action, self::ACTIONS)) {
            return $this->fail('err_generic');
        }

        Schema::ensure();
        $this->row = Repo::find($this->sid);
        $this->snap = Repo::snapshot($this->row);
        $whmcsStatus = isset($this->params['status']) ? $this->params['status'] : '';

        if ($action === 'status') {
            return $this->status($whmcsStatus);
        }
        if ($whmcsStatus !== 'Active' || !$this->row || !$this->row->instance_id || $this->row->state !== 'active') {
            return $this->fail($this->row && $this->row->state === 'provisioning' ? 'err_building' : 'err_unavailable');
        }
        $feature = self::ACTIONS[$action];
        if ($feature !== null) {
            $features = ViewModel::features($this->snap, $this->row);
            if (empty($features[$feature])) {
                return $this->fail('err_unavailable');
            }
        }

        try {
            $this->api = Client::fromParams($this->params);
            $method = 'act' . str_replace(' ', '', ucwords(str_replace('_', ' ', $action)));
            return $this->$method();
        } catch (ApiException $e) {
            Repo::log($this->sid, 'client_' . $action, false, $e->friendly(), null, $this->row->instance_id, 'client');
            if ($e->status() === 429) {
                return $this->fail('err_busy');
            }
            return $this->fail('err_generic');
        }
    }

    // ------------------------------------------------------------------ status

    private function status($whmcsStatus)
    {
        $state = ViewModel::state($this->row, $whmcsStatus);
        if ($state === 'provisioning' && $this->row && $this->row->instance_id && Cache::acquire('finalize:' . $this->sid, 8)) {
            try {
                Watcher::finalize(Client::fromParams($this->params), $this->row);
            } catch (\Throwable $e) {
            }
            $this->row = Repo::find($this->sid);
            $this->snap = Repo::snapshot($this->row);
            $state = ViewModel::state($this->row, $whmcsStatus);
        } elseif ($state === 'active') {
            $age = time() - (int) (isset($this->snap['synced_at']) ? $this->snap['synced_at'] : 0);
            if ($age > 8 && Cache::acquire('status:' . $this->sid, 8)) {
                try {
                    $inst = Provisioner::unwrap(Client::fromParams($this->params)->get('instances/' . rawurlencode($this->row->instance_id), [], ['low' => true]));
                    if (Provisioner::fenceOk($this->row, $inst)) {
                        $this->snap = Provisioner::snapshotOf($inst);
                        Repo::update($this->sid, ['snapshot' => json_encode($this->snap), 'last_sync_at' => Repo::now()]);
                    }
                } catch (ApiException $e) {
                    // Keep showing the cached snapshot.
                }
            }
        }
        $t = Lang::load();
        $ip = isset($this->snap['main_ip']) ? $this->snap['main_ip'] : '';
        return $this->ok([
            'state' => $state,
            'phase' => ViewModel::phase($this->snap),
            'status' => ViewModel::status($this->snap, $state, $t),
            'ip' => Util::clean($ip, 64),
            'hostname' => Util::clean(isset($this->snap['hostname']) ? $this->snap['hostname'] : '', 190),
        ]);
    }

    // ------------------------------------------------------------------ power & access

    private function actPower()
    {
        $op = isset($_POST['op']) ? (string) $_POST['op'] : '';
        if (!in_array($op, ['boot', 'shutdown', 'restart', 'poweroff'], true)) {
            return $this->fail('err_generic');
        }
        if ($wait = $this->cooldown('power', 15)) {
            return $wait;
        }
        $this->api->post($this->instancePath('power/' . $op));
        Repo::update($this->sid, ['last_sync_at' => null]);
        $this->snap['synced_at'] = 0;
        Repo::update($this->sid, ['snapshot' => json_encode($this->snap)]);
        Repo::log($this->sid, 'power_' . $op, true, 'Power ' . $op, null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_power_sent')]);
    }

    private function actPasswordReveal()
    {
        $hosting = Repo::hosting($this->sid);
        $password = $hosting && $hosting->password !== '' ? decrypt($hosting->password) : '';
        if ($password === '') {
            return $this->fail('err_no_password');
        }
        return $this->ok(['password' => $password]);
    }

    private function actPasswordReset()
    {
        if ($wait = $this->cooldown('password', 300)) {
            return $wait;
        }
        $password = (new Provisioner($this->params))->doResetPassword($this->row);
        if ($password === null) {
            return $this->fail('err_generic');
        }
        return $this->ok(['password' => $password, 'message' => Lang::t('js_password_reset_done')]);
    }

    private function actAppReveal()
    {
        $res = $this->api->get($this->instancePath('app'), [], ['low' => true]);
        $app = isset($res['app']) && is_array($res['app']) ? $res['app'] : null;
        if (!$app) {
            return $this->ok(['app' => null]);
        }
        $apps = (new Catalog())->apps();
        $slug = isset($app['slug']) ? $app['slug'] : '';
        return $this->ok(['app' => [
            'name' => Util::clean(isset($app['name']) ? $app['name'] : $slug, 60),
            'status' => Util::clean(isset($app['status']) ? $app['status'] : '', 30),
            'url' => self::safeUrl(isset($app['login_url']) ? $app['login_url'] : ''),
            'username' => Util::clean(isset($app['username']) ? $app['username'] : '', 120),
            'password' => isset($app['password']) && is_string($app['password']) ? $app['password'] : '',
            'note' => isset($apps[$slug]['setup_note']) ? $apps[$slug]['setup_note'] : '',
        ]]);
    }

    // ------------------------------------------------------------------ reinstall

    private function actReinstallOptions()
    {
        $catalog = new Catalog($this->api);
        $groups = [];
        foreach ($catalog->reinstallOptions($this->row->instance_id, (int) $this->row->package_id) as $os) {
            $key = $os['family'] ?: 'Other';
            if (!isset($groups[$key])) {
                $family = ViewModel::osFamily($key);
                $groups[$key] = ['family' => $key, 'icon' => $family, 'logo' => ViewModel::logo('os', $family), 'items' => []];
            }
            $groups[$key]['items'][] = ['id' => $os['id'], 'label' => $os['label'], 'eol' => $os['eol'], 'type' => $os['type']];
        }
        $apps = [];
        $features = ViewModel::features($this->snap, $this->row);
        $plan = $catalog->plan((int) $this->row->package_id);
        if ($features['apps'] && $plan) {
            foreach ($catalog->appsFor($plan) as $app) {
                $apps[] = [
                    'slug' => $app['slug'],
                    'name' => $app['name'],
                    'tagline' => $app['tagline'],
                    'category' => $app['category'],
                    'logo' => ViewModel::logo('apps', $app['slug']),
                ];
            }
        }
        return $this->ok(['groups' => array_values($groups), 'apps' => $apps]);
    }

    private function actReinstall()
    {
        $osId = isset($_POST['os_id']) ? (int) $_POST['os_id'] : 0;
        $appSlug = isset($_POST['app']) ? strtolower(trim((string) $_POST['app'])) : '';
        $catalog = new Catalog($this->api);
        $body = [];
        $label = '';
        if ($appSlug !== '') {
            $plan = $catalog->plan((int) $this->row->package_id);
            $apps = $plan ? $catalog->appsFor($plan) : [];
            $features = ViewModel::features($this->snap, $this->row);
            if (!$features['apps'] || !isset($apps[$appSlug])) {
                return $this->fail('err_generic');
            }
            $body['app_slug'] = $appSlug;
            $label = $apps[$appSlug]['name'];
        } else {
            $match = null;
            foreach ($catalog->reinstallOptions($this->row->instance_id, (int) $this->row->package_id) as $os) {
                if ($os['id'] === $osId) {
                    $match = $os;
                    break;
                }
            }
            if (!$match) {
                return $this->fail('err_generic');
            }
            $body['os_template_id'] = $match['id'];
            $body['os_name'] = $match['label'];
            $label = $match['label'];
        }
        if ($wait = $this->cooldown('reinstall', 300)) {
            return $wait;
        }
        $this->api->post($this->instancePath('reinstall'), $body, ['timeout' => 60]);
        Repo::update($this->sid, [
            'state' => 'provisioning',
            'app_slug' => $appSlug !== '' ? $appSlug : null,
            'os_label' => Util::clean($label, 190),
            'creating_at' => Repo::now(),
            'alerted_at' => null,
        ]);
        Repo::log($this->sid, 'reinstall', true, 'Reinstall: ' . $label, null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_reinstall_started')]);
    }

    // ------------------------------------------------------------------ graphs

    private function actMetrics()
    {
        $period = isset($_POST['period']) && in_array($_POST['period'], self::METRIC_PERIODS, true) ? $_POST['period'] : '1h';
        $data = Cache::remember('metrics:' . $this->sid . ':' . $period, 60, function () use ($period) {
            return self::shapeMetrics($this->api->get($this->instancePath('metrics'), ['period' => $period], ['low' => true]));
        });
        return $this->ok($data);
    }

    public static function shapeMetrics($raw)
    {
        $pct = function ($used, $total) {
            return $total > 0 ? round(100 * $used / $total, 1) : null;
        };
        $cur = isset($raw['current']) && is_array($raw['current']) ? $raw['current'] : [];
        $memUsed = (float) (isset($cur['memory_used_kb']) ? $cur['memory_used_kb'] : 0) * 1024;
        $memTotal = (float) (isset($cur['memory_total_kb']) ? $cur['memory_total_kb'] : 0) * 1024;
        $diskUsed = (float) (isset($cur['disk_used_bytes']) ? $cur['disk_used_bytes'] : 0);
        $diskTotal = (float) (isset($cur['disk_total_bytes']) ? $cur['disk_total_bytes'] : 0);

        $points = [];
        $prev = null;
        $history = isset($raw['history']) && is_array($raw['history']) ? $raw['history'] : [];
        usort($history, function ($a, $b) {
            return strcmp((string) (isset($a['created_at']) ? $a['created_at'] : ''), (string) (isset($b['created_at']) ? $b['created_at'] : ''));
        });
        foreach ($history as $h) {
            $ts = Util::ts(isset($h['created_at']) ? $h['created_at'] : null);
            if (!$ts) {
                continue;
            }
            $rx = (float) (isset($h['network_rx_bytes']) ? $h['network_rx_bytes'] : 0);
            $tx = (float) (isset($h['network_tx_bytes']) ? $h['network_tx_bytes'] : 0);
            $rxRate = null;
            $txRate = null;
            if ($prev && $ts > $prev[0]) {
                $dt = $ts - $prev[0];
                // Counters reset on reboot: a negative delta contributes nothing rather than a spike.
                $rxRate = $rx >= $prev[1] ? round(8 * ($rx - $prev[1]) / $dt) : 0;
                $txRate = $tx >= $prev[2] ? round(8 * ($tx - $prev[2]) / $dt) : 0;
            }
            $prev = [$ts, $rx, $tx];
            $points[] = [
                $ts * 1000,
                round((float) (isset($h['cpu_percent']) ? $h['cpu_percent'] : 0), 1),
                $pct((float) (isset($h['memory_used_kb']) ? $h['memory_used_kb'] : 0), (float) (isset($h['memory_total_kb']) ? $h['memory_total_kb'] : 0)),
                $pct((float) (isset($h['disk_used_bytes']) ? $h['disk_used_bytes'] : 0), (float) (isset($h['disk_total_bytes']) ? $h['disk_total_bytes'] : 0)),
                $rxRate,
                $txRate,
            ];
        }
        return [
            'current' => [
                'cpu' => round((float) (isset($cur['cpu_percent']) ? $cur['cpu_percent'] : 0), 1),
                'memPct' => $pct($memUsed, $memTotal),
                'memText' => Util::formatBytes($memUsed) . ' / ' . Util::formatBytes($memTotal),
                'diskPct' => $pct($diskUsed, $diskTotal),
                'diskText' => Util::formatBytes($diskUsed) . ' / ' . Util::formatBytes($diskTotal),
                'rxText' => Util::formatBytes(isset($cur['network_rx_bytes']) ? $cur['network_rx_bytes'] : 0),
                'txText' => Util::formatBytes(isset($cur['network_tx_bytes']) ? $cur['network_tx_bytes'] : 0),
            ],
            'points' => $points,
        ];
    }

    // ------------------------------------------------------------------ DDoS

    private function actDdos()
    {
        $period = isset($_POST['period']) && in_array($_POST['period'], self::DDOS_PERIODS, true) ? $_POST['period'] : 'live';
        $overview = Cache::remember('ddos:' . $this->sid . ':' . $period, $period === 'live' ? 12 : 120, function () use ($period) {
            $raw = $this->api->get($this->instancePath('ddos/overview'), ['period' => $period], ['low' => true]);
            $points = [];
            foreach ((array) (isset($raw['points']) ? $raw['points'] : []) as $p) {
                $ts = Util::ts(isset($p['time']) ? $p['time'] : null);
                if ($ts) {
                    $points[] = [$ts * 1000, (int) (isset($p['drop_bps']) ? $p['drop_bps'] : 0), (int) (isset($p['pass_bps']) ? $p['pass_bps'] : 0)];
                }
            }
            return ['supported' => !isset($raw['supported']) || !empty($raw['supported']), 'points' => $points];
        });
        $incidents = Cache::remember('ddos-incidents:' . $this->sid, 300, function () {
            $raw = $this->api->get($this->instancePath('ddos/incidents'), ['page' => 1], ['low' => true]);
            $items = [];
            foreach ((array) (isset($raw['items']) ? $raw['items'] : []) as $i) {
                $items[] = [
                    'start' => Util::date(isset($i['started_at']) ? $i['started_at'] : null, true),
                    'duration' => self::duration((int) (isset($i['duration_seconds']) ? $i['duration_seconds'] : 0)),
                    'vectors' => Util::clean(implode(', ', array_map('strval', (array) (isset($i['vectors']) ? $i['vectors'] : []))), 120),
                    'peak' => Util::clean(isset($i['peak']) ? $i['peak'] : '', 30),
                ];
            }
            return ['items' => $items, 'total' => (int) (isset($raw['total_items']) ? $raw['total_items'] : count($items))];
        });
        $settings = $this->api->get($this->instancePath('ddos/settings'), [], ['low' => true]);
        return $this->ok([
            'supported' => $overview['supported'],
            'points' => $overview['points'],
            'incidents' => $incidents['items'],
            'incidentTotal' => $incidents['total'],
            'settings' => [
                'email' => !empty($settings['email_notifications']),
            ],
        ]);
    }

    /** Only the email toggle is exposed; webhook and affiliate settings are never sent. */
    private function actDdosSave()
    {
        $body = ['email_notifications' => !empty($_POST['email']) && $_POST['email'] !== '0'];
        if ($wait = $this->cooldown('ddos_save', 5)) {
            return $wait;
        }
        $this->api->put($this->instancePath('ddos/settings'), $body);
        Repo::log($this->sid, 'ddos_settings', true, 'Attack alert settings updated', null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_saved')]);
    }

    // ------------------------------------------------------------------ firewall

    private function actFirewall()
    {
        list($ipId, $address) = $this->shieldIp();
        if (!$ipId) {
            return $this->ok(['available' => false]);
        }
        return $this->ok(['available' => true, 'address' => $address] + $this->firewallState($ipId));
    }

    private function actFirewallAdd()
    {
        list($ipId) = $this->shieldIp();
        if (!$ipId) {
            return $this->fail('err_unavailable');
        }
        $direction = isset($_POST['direction']) ? (string) $_POST['direction'] : 'in';
        $action = isset($_POST['rule_action']) ? (string) $_POST['rule_action'] : '';
        $protocol = isset($_POST['protocol']) ? (string) $_POST['protocol'] : '';
        $source = trim(isset($_POST['source']) ? (string) $_POST['source'] : '');
        $port = trim(isset($_POST['port']) ? (string) $_POST['port'] : '');
        if (!in_array($direction, ['in', 'out'], true) || !in_array($action, ['allow', 'block'], true) || !in_array($protocol, ['tcp', 'udp', 'any'], true)) {
            return $this->fail('err_rule');
        }
        if ($source !== '' && !Util::validCidr($source)) {
            return $this->fail('err_cidr');
        }
        $ports = self::parsePorts($port);
        if ($ports === false) {
            return $this->fail('err_port');
        }
        if ($ports && $protocol === 'any') {
            return $this->fail('err_port_protocol');
        }
        $state = $this->firewallState($ipId);
        if (count($state['rules']) >= 50) {
            return $this->fail('err_rule_limit');
        }
        if ($wait = $this->cooldown('firewall', 2)) {
            return $wait;
        }
        $this->api->post('shield/ips/' . (int) $ipId . '/rules', [
            'direction' => $direction,
            'action' => $action === 'block' ? 'deny' : 'allow',
            'protocol' => $protocol,
            'source_type' => 'ip',
            'source_value' => $source !== '' ? $source : '0.0.0.0/0',
            'port_start' => $ports ? $ports[0] : null,
            'port_end' => $ports ? $ports[1] : null,
        ]);
        Repo::log($this->sid, 'firewall_add', true, ucfirst($action) . ' ' . $direction . ' ' . $protocol . ($ports ? ' port ' . $port : '') . ' ' . ($source ?: 'anywhere'), null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_rule_added')] + $this->firewallState($ipId));
    }

    /** "" => [] (all ports), "22" => [22, 22], "8000-8100" => [8000, 8100], invalid => false. */
    public static function parsePorts($value)
    {
        $value = str_replace(' ', '', (string) $value);
        if ($value === '') {
            return [];
        }
        if (!preg_match('/^([0-9]{1,5})(?:[-:]([0-9]{1,5}))?$/', $value, $m)) {
            return false;
        }
        $start = (int) $m[1];
        $end = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $start;
        if ($start < 1 || $end > 65535 || $end < $start) {
            return false;
        }
        return [$start, $end];
    }

    private function actFirewallDelete()
    {
        list($ipId) = $this->shieldIp();
        $ruleId = isset($_POST['rule_id']) ? (int) $_POST['rule_id'] : 0;
        if (!$ipId || $ruleId <= 0) {
            return $this->fail('err_generic');
        }
        $owned = false;
        foreach ($this->firewallState($ipId)['rules'] as $rule) {
            if ($rule['id'] === $ruleId) {
                $owned = true;
                break;
            }
        }
        if (!$owned) {
            return $this->fail('err_generic');
        }
        if ($wait = $this->cooldown('firewall', 2)) {
            return $wait;
        }
        $this->api->delete('shield/ips/' . (int) $ipId . '/rules/' . $ruleId);
        Repo::log($this->sid, 'firewall_delete', true, 'Rule #' . $ruleId . ' deleted', null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_rule_deleted')] + $this->firewallState($ipId));
    }

    private function actFirewallPolicy()
    {
        list($ipId) = $this->shieldIp();
        $in = isset($_POST['in']) ? (string) $_POST['in'] : '';
        $out = isset($_POST['out']) ? (string) $_POST['out'] : '';
        $enabled = !empty($_POST['enabled']) && $_POST['enabled'] !== '0';
        if (!$ipId || !in_array($in, ['allow', 'block'], true) || !in_array($out, ['allow', 'block'], true)) {
            return $this->fail('err_generic');
        }
        if ($wait = $this->cooldown('firewall', 2)) {
            return $wait;
        }
        $this->api->put('shield/ips/' . (int) $ipId . '/policy', [
            'inbound_default' => $in === 'block' ? 'drop' : 'accept',
            'outbound_default' => $out === 'block' ? 'drop' : 'accept',
            'enabled' => $enabled,
        ]);
        Repo::log($this->sid, 'firewall_policy', true, 'Firewall ' . ($enabled ? 'on' : 'off') . ', default in ' . $in . ', out ' . $out, null, $this->row->instance_id, 'client');
        return $this->ok(['message' => Lang::t('js_saved')] + $this->firewallState($ipId));
    }

    /** Shield IP record of this server's primary address, resolved server-side. */
    private function shieldIp()
    {
        $key = 'shieldip:' . $this->sid . ':' . $this->row->instance_id;
        $found = Cache::remember($key, 86400, function () {
            $candidates = [];
            foreach ((array) $this->api->get('shield/ips', [], ['low' => true]) as $ip) {
                if (is_array($ip) && isset($ip['instance_id']) && (string) $ip['instance_id'] === (string) $this->row->instance_id) {
                    $candidates[] = $ip;
                }
            }
            usort($candidates, function ($a, $b) {
                return (int) !empty($b['is_primary']) - (int) !empty($a['is_primary']);
            });
            return $candidates ? [(int) $candidates[0]['id'], (string) $candidates[0]['address']] : [0, ''];
        });
        return is_array($found) ? $found : [0, ''];
    }

    private function firewallState($ipId)
    {
        $raw = $this->api->get('shield/ips/' . (int) $ipId, [], ['low' => true]);
        if (isset($raw['instance_id']) && (string) $raw['instance_id'] !== (string) $this->row->instance_id) {
            throw new ApiException('Shield IP does not belong to this server', 403);
        }
        $policy = isset($raw['policy']) && is_array($raw['policy']) ? $raw['policy'] : [];
        $norm = function ($v) {
            $v = strtolower((string) $v);
            return in_array($v, ['block', 'drop', 'deny', 'reject'], true) ? 'block' : 'allow';
        };
        $rules = [];
        foreach ((array) (isset($raw['rules']) ? $raw['rules'] : []) as $r) {
            if (!is_array($r) || !isset($r['id'])) {
                continue;
            }
            $start = isset($r['port_start']) && $r['port_start'] !== null && $r['port_start'] !== '' ? (int) $r['port_start'] : null;
            $end = isset($r['port_end']) && $r['port_end'] !== null && $r['port_end'] !== '' ? (int) $r['port_end'] : $start;
            $type = strtolower(isset($r['source_type']) ? (string) $r['source_type'] : 'ip');
            $value = Util::clean(isset($r['source_value']) ? $r['source_value'] : '', 64);
            $rules[] = [
                'id' => (int) $r['id'],
                'direction' => isset($r['direction']) && $r['direction'] === 'out' ? 'out' : 'in',
                'action' => $norm(isset($r['action']) ? $r['action'] : 'allow'),
                'protocol' => Util::clean(strtolower(isset($r['protocol']) ? $r['protocol'] : 'any'), 8),
                'port' => $start === null ? '' : ($end && $end !== $start ? $start . '-' . $end : (string) $start),
                'source' => $type === 'ip' ? $value : strtoupper($type) . ' ' . $value,
                'anywhere' => $type === 'ip' && in_array($value, ['', '0.0.0.0/0', '::/0'], true),
            ];
        }
        return [
            'enabled' => !empty($policy['enabled']),
            'policy' => [
                'in' => $norm(isset($policy['inbound_default']) ? $policy['inbound_default'] : 'accept'),
                'out' => $norm(isset($policy['outbound_default']) ? $policy['outbound_default'] : 'accept'),
            ],
            'rules' => $rules,
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function instancePath($suffix)
    {
        return 'instances/' . rawurlencode($this->row->instance_id) . '/' . $suffix;
    }

    /** Per-service cooldown shared across sessions; returns a failure response while cooling down. */
    private function cooldown($group, $seconds)
    {
        $key = 'cd:' . $this->sid . ':' . $group;
        if (Cache::acquire($key, $seconds)) {
            return null;
        }
        return $this->fail('err_cooldown', ['seconds' => max(1, Cache::remaining($key))]);
    }

    private function ok(array $data)
    {
        return ['ok' => true, 'data' => $data];
    }

    private function fail($key, array $replace = [])
    {
        return ['ok' => false, 'error' => Lang::t($key, $replace), 'code' => $key];
    }

    private static function safeUrl($url)
    {
        $url = trim((string) $url);
        return preg_match('#^https?://[^\s"<>]+$#i', $url) ? $url : '';
    }

    private static function duration($seconds)
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
        }
        return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
    }
}
