<?php

namespace WHMCS\Module\Server\Evorxa;

/**
 * Builds the single {$evx} variable tree used by templates/overview.tpl.
 *
 * Everything is formatted here, so templates only need {$var|escape}, {if} and {foreach}
 * (WHMCS's Smarty policy allows no PHP functions or modifiers). The first paint uses the
 * locally cached snapshot only; the browser then asks for live data.
 *
 * Deliberately never exposed: API token, upstream project/ids, cost and billing data,
 * console details, raw upstream errors, and the root password (only on "Reveal").
 */
class ViewModel
{
    const VERSION = '1.0.0';

    public static function build(array $params)
    {
        Schema::ensure();
        $sid = (int) $params['serviceid'];
        $row = Repo::find($sid);
        $snap = Repo::snapshot($row);
        $hosting = Repo::hosting($sid);
        $t = Lang::load();
        $state = self::state($row, isset($params['status']) ? $params['status'] : ($hosting ? $hosting->domainstatus : ''));
        $features = self::features($snap, $row);

        $plan = null;
        if ($row && $row->package_id) {
            try {
                $plan = (new Catalog())->plan($row->package_id);
            } catch (\Throwable $e) {
                $plan = null;
            }
        }

        $osLabel = $row && $row->os_label ? $row->os_label : (isset($snap['os']) ? $snap['os'] : '');
        $windows = stripos($osLabel, 'windows') !== false;
        $ip = !empty($snap['main_ip']) ? $snap['main_ip'] : ($hosting ? $hosting->dedicatedip : '');
        $hostname = !empty($snap['hostname']) ? $snap['hostname'] : ($hosting ? $hosting->domain : '');
        $username = $hosting && $hosting->username ? $hosting->username : (isset($snap['username']) ? $snap['username'] : 'root');

        $webRoot = rtrim((string) parse_url((string) \App::getSystemURL(), PHP_URL_PATH), '/');
        $apiUrl = $webRoot . '/clientarea.php?action=productdetails&id=' . $sid . '&modop=custom&a=api';
        $status = self::status($snap, $state, $t);

        $vm = [
            'v' => self::VERSION,
            'tpl' => View::templatesDir(),
            'assets' => $webRoot . '/modules/servers/evorxa/templates/assets',
            'serviceId' => $sid,
            'state' => $state,
            'rtl' => Lang::isRtl(),
            't' => $t,
            'server' => [
                'hostname' => Util::clean($hostname, 190),
                'ip' => Util::clean($ip, 64),
                'os' => Util::clean($osLabel, 120),
                'osFamily' => self::osFamily($osLabel),
                'windows' => $windows,
                'username' => Util::clean($username, 60),
                'hasPassword' => $hosting && $hosting->password !== '',
                'location' => Util::clean(isset($snap['location']) ? $snap['location'] : '', 80),
                'flag' => self::flag(isset($snap['location_country_code']) ? $snap['location_country_code'] : ''),
                'connect' => $ip ? ($windows ? $ip : 'ssh ' . Util::clean($username, 60) . '@' . $ip) : '',
            ],
            'status' => $status,
            'specs' => self::specs($snap, $plan, $t),
            'ips' => self::ips($snap, $t),
            'app' => self::app($row, $snap),
            'features' => $features,
            'hasTabs' => $features['graphs'] || $features['ddos'] || $features['firewall'] || $features['reinstall'],
            'cancelPending' => $row ? Keeper::pendingEndOfPeriodCancel($sid) : false,
        ];

        $vm['bootJson'] = json_encode([
            'api' => $apiUrl,
            'token' => function_exists('generate_token') ? generate_token('plain') : '',
            'state' => $state,
            'status' => $status,
            'features' => $features,
            'windows' => $windows,
            'hostname' => $vm['server']['hostname'],
            'rtl' => $vm['rtl'],
            't' => self::jsStrings($t),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

        return $vm;
    }

    /** Used when build() failed: enough for error.tpl. */
    public static function minimal(array $params)
    {
        return ['t' => Lang::load(), 'tpl' => View::templatesDir(), 'serviceId' => (int) $params['serviceid']];
    }

    /** What the panel shows: pending, provisioning, active, suspended, closed, failed, none. */
    public static function state($row, $whmcsStatus)
    {
        $whmcsStatus = (string) $whmcsStatus;
        if (in_array($whmcsStatus, ['Terminated', 'Cancelled', 'Fraud', 'Completed'], true)) {
            return 'closed';
        }
        if ($whmcsStatus === 'Suspended') {
            return 'suspended';
        }
        if (!$row) {
            return $whmcsStatus === 'Pending' ? 'pending' : 'none';
        }
        switch ($row->state) {
            case 'creating':
            case 'unknown':
            case 'provisioning':
                return 'provisioning';
            case 'failed':
                return 'failed';
            case 'terminated':
                return 'closed';
            case 'active':
                return $row->instance_id ? 'active' : 'none';
            default:
                return $whmcsStatus === 'Pending' ? 'pending' : 'none';
        }
    }

    /** Client features: reseller setting AND not locked upstream. */
    public static function features(array $snap, $row)
    {
        $allow = function ($key) use ($snap) {
            return !array_key_exists($key, $snap) || !empty($snap[$key]);
        };
        $locked = function ($key) use ($snap) {
            return !empty($snap[$key]);
        };
        return [
            'power' => Settings::feature('power') && ($allow('allow_reboot') || $allow('allow_stop')),
            'password' => Settings::feature('password') && $allow('allow_reset_password'),
            'reinstall' => Settings::feature('reinstall') && $allow('allow_reinstall'),
            'apps' => Settings::feature('reinstall') && Settings::feature('apps') && $allow('allow_reinstall'),
            'graphs' => Settings::feature('graphs') && !$locked('lock_monitoring'),
            'ddos' => Settings::feature('ddos') && !$locked('lock_ddos'),
            'firewall' => Settings::feature('firewall') && !$locked('lock_firewall'),
        ];
    }

    /** Live status pill: label + tone (ok, warn, off, busy, bad). */
    public static function status(array $snap, $state, array $t)
    {
        if ($state !== 'active') {
            $map = [
                'provisioning' => 'busy', 'pending' => 'warn', 'suspended' => 'bad',
                'closed' => 'off', 'failed' => 'bad', 'none' => 'off',
            ];
            return [
                'key' => $state,
                'label' => self::tr($t, 'state_' . $state, ucfirst($state)),
                'tone' => isset($map[$state]) ? $map[$state] : 'off',
                'running' => false,
                'busy' => $state === 'provisioning',
            ];
        }
        $raw = strtolower((string) (isset($snap['status']) ? $snap['status'] : 'unknown'));
        if (in_array($raw, Provisioner::BUILDING, true)) {
            $raw = 'provisioning';
        }
        $tones = [
            'running' => 'ok', 'stopped' => 'off', 'shutdown' => 'off', 'off' => 'off', 'poweroff' => 'off',
            'starting' => 'busy', 'booting' => 'busy', 'stopping' => 'busy', 'restarting' => 'busy', 'rebooting' => 'busy',
            'shutting_down' => 'busy', 'provisioning' => 'busy', 'suspended' => 'bad', 'error' => 'bad',
        ];
        $tone = isset($tones[$raw]) ? $tones[$raw] : 'warn';
        return [
            'key' => $raw,
            'label' => self::tr($t, 'status_' . $raw, ucwords(str_replace('_', ' ', $raw))),
            'tone' => $tone,
            'running' => $raw === 'running',
            'busy' => $tone === 'busy',
        ];
    }

    private static function specs(array $snap, $plan, array $t)
    {
        $vcpu = isset($snap['vcpu']) ? $snap['vcpu'] : ($plan ? $plan['vcpu'] : null);
        $ram = isset($snap['ram']) ? $snap['ram'] : ($plan ? $plan['ram'] : null);
        $disk = isset($snap['storage']) ? $snap['storage'] : ($plan ? $plan['storage'] : null);
        $uplink = isset($snap['uplink']) ? $snap['uplink'] : ($plan && isset($plan['uplink']) ? $plan['uplink'] : null);
        $specs = [];
        if ($vcpu !== null) {
            $specs[] = ['icon' => 'cpu', 'label' => self::tr($t, 'spec_cpu', 'vCPU'), 'value' => (int) $vcpu . ' ' . self::tr($t, 'unit_cores', 'cores')];
        }
        if ($ram !== null) {
            $specs[] = ['icon' => 'ram', 'label' => self::tr($t, 'spec_ram', 'Memory'), 'value' => (int) $ram . ' GB'];
        }
        if ($disk !== null) {
            $specs[] = ['icon' => 'disk', 'label' => self::tr($t, 'spec_disk', 'NVMe storage'), 'value' => (int) $disk . ' GB'];
        }
        if ($uplink) {
            $specs[] = ['icon' => 'net', 'label' => self::tr($t, 'spec_network', 'Network'), 'value' => Util::clean($uplink, 40)];
        }
        return $specs;
    }

    private static function ips(array $snap, array $t)
    {
        $list = [];
        foreach ((array) (isset($snap['ip_addresses']) ? $snap['ip_addresses'] : []) as $ip) {
            $list[] = [
                'address' => Util::clean($ip['address'], 64),
                'type' => stripos((string) $ip['type'], '6') !== false ? 'IPv6' : 'IPv4',
                'rdns' => Util::clean(isset($ip['rdns']) ? $ip['rdns'] : '', 190),
                'primary' => !empty($ip['is_primary']),
            ];
        }
        usort($list, function ($a, $b) {
            return (int) $b['primary'] - (int) $a['primary'];
        });
        return $list;
    }

    private static function app($row, array $snap)
    {
        $slug = $row && $row->app_slug ? $row->app_slug : (isset($snap['current_app']['slug']) ? $snap['current_app']['slug'] : '');
        if (!$slug) {
            return null;
        }
        $name = isset($snap['current_app']['name']) && $snap['current_app']['name'] ? $snap['current_app']['name'] : '';
        $note = '';
        try {
            $apps = (new Catalog())->apps();
            if (isset($apps[$slug])) {
                $name = $name ?: $apps[$slug]['name'];
                $note = $apps[$slug]['setup_note'];
            }
        } catch (\Throwable $e) {
        }
        return ['slug' => Util::clean($slug, 64), 'name' => Util::clean($name ?: $slug, 60), 'note' => $note];
    }

    public static function osFamily($label)
    {
        $label = strtolower((string) $label);
        foreach (['ubuntu', 'debian', 'almalinux', 'rocky', 'centos', 'windows', 'fedora', 'alpine', 'arch'] as $family) {
            if (strpos($label, $family) !== false) {
                return $family;
            }
        }
        return 'linux';
    }

    /** Regional-indicator flag emoji for a two-letter country code. */
    public static function flag($code)
    {
        $code = strtoupper(preg_replace('/[^a-z]/i', '', (string) $code));
        if (strlen($code) !== 2 || !function_exists('mb_chr')) {
            return '';
        }
        return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
    }

    private static function jsStrings(array $t)
    {
        $js = [];
        foreach ($t as $key => $value) {
            if (strpos($key, 'js_') === 0 || strpos($key, 'status_') === 0 || strpos($key, 'state_') === 0) {
                $js[$key] = $value;
            }
        }
        return $js;
    }

    private static function tr(array $t, $key, $fallback)
    {
        return isset($t[$key]) ? $t[$key] : $fallback;
    }
}
