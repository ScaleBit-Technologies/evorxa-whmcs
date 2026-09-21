<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * Reseller-wide settings, edited in Addons > Evorxa Manager > Settings.
 *
 * Kept in the module's own table so deactivating the addon cannot wipe them.
 */
class Settings
{
    const FEATURES = ['power', 'password', 'reinstall', 'apps', 'graphs', 'ddos', 'firewall'];

    private static $cache = null;

    public static function defaults()
    {
        return [
            'project_id' => '',
            'hostname_suffix' => self::defaultSuffix(),
            'low_balance' => '20',
            'provision_timeout' => '30',
            'install_prefix' => '',
            'feature_power' => '1',
            'feature_password' => '1',
            'feature_reinstall' => '1',
            'feature_apps' => '1',
            'feature_graphs' => '1',
            'feature_ddos' => '1',
            'feature_firewall' => '1',
        ];
    }

    public static function all()
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        Schema::ensure();
        $values = self::defaults();
        foreach (Capsule::table(Schema::SETTINGS)->get() as $row) {
            $values[$row->name] = (string) $row->value;
        }
        if ($values['hostname_suffix'] === '') {
            $values['hostname_suffix'] = self::defaultSuffix();
        }
        return self::$cache = $values;
    }

    public static function get($name)
    {
        $all = self::all();
        return isset($all[$name]) ? $all[$name] : null;
    }

    public static function set($name, $value)
    {
        Schema::ensure();
        $value = (string) $value;
        $updated = Capsule::table(Schema::SETTINGS)->where('name', $name)->update(['value' => $value]);
        if (!$updated && !Capsule::table(Schema::SETTINGS)->where('name', $name)->count()) {
            Capsule::table(Schema::SETTINGS)->insert(['name' => $name, 'value' => $value]);
        }
        self::$cache = null;
    }

    public static function feature($name)
    {
        return self::get('feature_' . $name) === '1';
    }

    public static function projectId()
    {
        return (int) self::get('project_id');
    }

    /**
     * Random per-install prefix for upstream instance names, so two WHMCS installs
     * sharing one Evorxa account can never adopt each other's servers.
     */
    public static function installPrefix()
    {
        $prefix = (string) self::get('install_prefix');
        if (!preg_match('/^[a-z0-9]{4,8}$/', $prefix)) {
            $prefix = 'w' . substr(bin2hex(random_bytes(4)), 0, 5);
            self::set('install_prefix', $prefix);
        }
        return $prefix;
    }

    /** "kero-dev.tech" from the WHMCS Domain setting, else the System URL minus its first label. */
    public static function defaultSuffix()
    {
        $candidates = [];
        try {
            $rows = Capsule::table('tblconfiguration')->whereIn('setting', ['Domain', 'SystemURL'])->pluck('value', 'setting');
            foreach (['Domain', 'SystemURL'] as $key) {
                if (!empty($rows[$key])) {
                    $candidates[] = $rows[$key];
                }
            }
        } catch (\Throwable $e) {
        }
        foreach ($candidates as $url) {
            $host = parse_url(strpos($url, '://') === false ? 'https://' . $url : $url, PHP_URL_HOST);
            if (!$host || filter_var($host, FILTER_VALIDATE_IP)) {
                continue;
            }
            $host = strtolower(preg_replace('/^www\./i', '', $host));
            $labels = explode('.', $host);
            if (count($labels) > 2) {
                array_shift($labels);
            }
            if (count($labels) >= 2) {
                return implode('.', $labels);
            }
        }
        return '';
    }
}
