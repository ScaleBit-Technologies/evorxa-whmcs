<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * Small shared cache on WHMCS's own tbltransientdata table.
 *
 * Shared across requests and processes (unlike $_SESSION), so it also backs
 * per-service action cooldowns and the cron "every N minutes" gates.
 */
class Cache
{
    const PREFIX = 'evx:';

    private static $memo = [];

    public static function get($key)
    {
        if (array_key_exists($key, self::$memo)) {
            return self::$memo[$key];
        }
        try {
            $row = Capsule::table('tbltransientdata')
                ->where('name', self::PREFIX . $key)
                ->orderBy('expires', 'desc')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$row || (int) $row->expires < time()) {
            return null;
        }
        $value = json_decode($row->data, true);
        self::$memo[$key] = $value;
        return $value;
    }

    public static function set($key, $value, $ttl)
    {
        self::$memo[$key] = $value;
        $name = self::PREFIX . $key;
        $data = ['data' => json_encode($value), 'expires' => time() + (int) $ttl];
        try {
            $updated = Capsule::table('tbltransientdata')->where('name', $name)->update($data);
            if (!$updated) {
                Capsule::table('tbltransientdata')->insert(['name' => $name] + $data);
            }
        } catch (\Throwable $e) {
            // Best effort: a cache miss only costs an extra API call.
        }
    }

    public static function delete($key)
    {
        unset(self::$memo[$key]);
        try {
            Capsule::table('tbltransientdata')->where('name', self::PREFIX . $key)->delete();
        } catch (\Throwable $e) {
        }
    }

    public static function remember($key, $ttl, callable $fn)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $fn();
        if ($value !== null) {
            self::set($key, $value, $ttl);
        }
        return $value;
    }

    /**
     * Take a slot for $ttl seconds. Returns false while a previous slot is still running.
     * Serialised with a MySQL named lock, so two simultaneous clicks cannot both win.
     */
    public static function acquire($key, $ttl)
    {
        $name = self::PREFIX . $key;
        $lock = 'evx_' . md5($name);
        if (!self::lock($lock, 3)) {
            return false;
        }
        try {
            $busy = Capsule::table('tbltransientdata')
                ->where('name', $name)
                ->where('expires', '>=', time())
                ->count();
            if ($busy) {
                return false;
            }
            Capsule::table('tbltransientdata')->where('name', $name)->delete();
            Capsule::table('tbltransientdata')->insert([
                'name' => $name,
                'data' => '1',
                'expires' => time() + (int) $ttl,
            ]);
            unset(self::$memo[$key]);
            return true;
        } catch (\Throwable $e) {
            return false;
        } finally {
            self::unlock($lock);
        }
    }

    /** Seconds left on a slot taken with acquire(); 0 when free. */
    public static function remaining($key)
    {
        try {
            $row = Capsule::table('tbltransientdata')
                ->where('name', self::PREFIX . $key)
                ->orderBy('expires', 'desc')
                ->first();
        } catch (\Throwable $e) {
            return 0;
        }
        return $row ? max(0, (int) $row->expires - time()) : 0;
    }

    public static function lock($name, $wait = 0)
    {
        try {
            $rows = Capsule::select('SELECT GET_LOCK(?, ?) AS l', [$name, (int) $wait]);
            return !empty($rows) && (int) $rows[0]->l === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function unlock($name)
    {
        try {
            Capsule::select('SELECT RELEASE_LOCK(?) AS l', [$name]);
        } catch (\Throwable $e) {
        }
    }
}
