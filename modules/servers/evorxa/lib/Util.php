<?php

namespace WHMCS\Module\Server\Evorxa;

class Util
{
    /** WHMCS billing cycle => Evorxa billing cycle when the product follows the client's term. */
    const CYCLE_MAP = [
        'monthly' => 'monthly',
        'quarterly' => 'monthly',
        'semi-annually' => '6months',
        'semiannually' => '6months',
        'annually' => 'yearly',
        'biennially' => 'yearly',
        'triennially' => 'yearly',
    ];

    const CYCLE_DAYS = ['monthly' => 30, '6months' => 182, 'yearly' => 365];

    /** Variants that only describe the image, not a different product. */
    const NEUTRAL_VARIANTS = ['', 'minimal', 'latest', 'eol', 'evaluation', 'standard', 'server'];

    public static function upstreamCycle($whmcsCycle, $mode)
    {
        $key = strtolower(trim((string) $whmcsCycle));
        if (!isset(self::CYCLE_MAP[$key])) {
            return null; // Free Account / One Time: refuse, upstream would bill forever.
        }
        return $mode === 'monthly' ? 'monthly' : self::CYCLE_MAP[$key];
    }

    /** Stable key for an OS template, e.g. ubuntu-24.04, debian-12, windows-2022. */
    public static function osSlug($groupName, array $template)
    {
        $family = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) $groupName));
        if ($family === '') {
            $family = strtolower(preg_replace('/[^a-z0-9]+/i', '', (string) (isset($template['name']) ? $template['name'] : 'os')));
        }
        $version = '';
        if (preg_match('/[0-9]+(\.[0-9]+)*/', (string) (isset($template['version']) ? $template['version'] : ''), $m)) {
            $version = $m[0];
        }
        $slug = $family . ($version !== '' ? '-' . $version : '-' . (int) (isset($template['id']) ? $template['id'] : 0));
        $variant = strtolower(trim((string) (isset($template['variant']) ? $template['variant'] : '')));
        if (!in_array($variant, self::NEUTRAL_VARIANTS, true)) {
            $slug .= '-' . preg_replace('/[^a-z0-9]+/', '', $variant);
        }
        return $slug;
    }

    /** "Ubuntu Server 24.04 LTS (Noble Numbat)", "CentOS 7 (EOL)". */
    public static function osLabel(array $template)
    {
        $label = trim((isset($template['name']) ? $template['name'] : '') . ' ' . (isset($template['version']) ? $template['version'] : ''));
        $variant = strtolower(trim((string) (isset($template['variant']) ? $template['variant'] : '')));
        if ($variant === 'eol' || !empty($template['eol'])) {
            $label .= ' (EOL)';
        } elseif (!in_array($variant, self::NEUTRAL_VARIANTS, true)) {
            $label .= ' ' . $template['variant'];
        }
        return $label;
    }

    public static function isEol(array $template)
    {
        return !empty($template['eol']) || strtolower(trim((string) (isset($template['variant']) ? $template['variant'] : ''))) === 'eol';
    }

    public static function validLabel($label)
    {
        return (bool) preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/', $label);
    }

    public static function validFqdn($host)
    {
        if (strlen($host) > 253 || strpos($host, '.') === false) {
            return false;
        }
        foreach (explode('.', $host) as $label) {
            if (!self::validLabel($label)) {
                return false;
            }
        }
        return (bool) preg_match('/[a-z]/', substr($host, strrpos($host, '.') + 1));
    }

    /**
     * Hostname sent to Evorxa (it also becomes the reverse DNS).
     * A full FQDN from the client is kept, a single label gets the reseller suffix,
     * anything else becomes vps{serviceid}.{suffix}.
     */
    public static function hostname($requested, $serviceId, $suffix)
    {
        $suffix = strtolower(trim((string) $suffix, " .\t\n\r"));
        $requested = strtolower(trim((string) $requested, " .\t\n\r"));
        if ($requested !== '' && self::validFqdn($requested)) {
            return $requested;
        }
        $label = $requested !== '' && self::validLabel($requested) ? $requested : 'vps' . (int) $serviceId;
        return $suffix !== '' ? $label . '.' . $suffix : $label;
    }

    public static function formatBytes($bytes, $decimals = 1)
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = 0;
        while (abs($bytes) >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return rtrim(rtrim(number_format($bytes, $i === 0 ? 0 : $decimals, '.', ''), '0'), '.') . ' ' . $units[$i];
    }

    public static function formatCents($cents)
    {
        $negative = $cents < 0;
        return ($negative ? '-' : '') . '$' . number_format(abs((int) $cents) / 100, 2);
    }

    public static function ts($iso)
    {
        if (!$iso) {
            return null;
        }
        $ts = strtotime((string) $iso);
        return $ts === false ? null : $ts;
    }

    public static function date($iso, $withTime = false)
    {
        $ts = self::ts($iso);
        if (!$ts) {
            return '';
        }
        return $withTime ? gmdate('Y-m-d H:i', $ts) . ' UTC' : gmdate('Y-m-d', $ts);
    }

    /** Strip control characters from upstream strings before they reach templates or emails. */
    public static function clean($value, $max = 255)
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value);
        return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
    }

    public static function validCidr($cidr)
    {
        $cidr = trim((string) $cidr);
        if ($cidr === '') {
            return false;
        }
        $parts = explode('/', $cidr, 2);
        $ip = $parts[0];
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $max = 32;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $max = 128;
        } else {
            return false;
        }
        if (!isset($parts[1])) {
            return true;
        }
        return ctype_digit($parts[1]) && (int) $parts[1] >= 0 && (int) $parts[1] <= $max;
    }

    /** One OpenSSH public key line, e.g. "ssh-ed25519 AAAA... comment". */
    public static function validSshKey($key)
    {
        $key = trim(preg_replace('/\s+/', ' ', (string) $key));
        return (bool) preg_match('#^(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-(ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com) [A-Za-z0-9+/]+={0,3}( [^\r\n]{0,200})?$#', $key);
    }
}
