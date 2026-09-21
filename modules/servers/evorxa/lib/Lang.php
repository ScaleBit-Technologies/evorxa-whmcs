<?php

namespace WHMCS\Module\Server\Evorxa;

/**
 * Client-facing strings: lang/english.php, then lang/<language>.php, then
 * lang/overrides/<language>.php (for resellers; survives module updates if kept out of the zip).
 */
class Lang
{
    const RTL = ['arabic', 'farsi', 'hebrew', 'persian', 'urdu'];

    private static $loaded = [];

    public static function current()
    {
        try {
            if (class_exists('\Lang') && method_exists('\Lang', 'getName')) {
                $name = \Lang::getName();
                if ($name) {
                    return strtolower($name);
                }
            }
        } catch (\Throwable $e) {
        }
        if (!empty($_SESSION['Language'])) {
            return strtolower($_SESSION['Language']);
        }
        return 'english';
    }

    public static function load($language = null)
    {
        $language = preg_replace('/[^a-z_-]/', '', strtolower($language ?: self::current()));
        if (isset(self::$loaded[$language])) {
            return self::$loaded[$language];
        }
        $dir = dirname(__DIR__) . '/lang';
        $strings = self::file($dir . '/english.php');
        if ($language !== 'english') {
            $strings = array_merge($strings, self::file($dir . '/' . $language . '.php'));
        }
        $strings = array_merge($strings, self::file($dir . '/overrides/english.php'));
        if ($language !== 'english') {
            $strings = array_merge($strings, self::file($dir . '/overrides/' . $language . '.php'));
        }
        return self::$loaded[$language] = $strings;
    }

    public static function isRtl($language = null)
    {
        return in_array($language ?: self::current(), self::RTL, true);
    }

    public static function t($key, array $replace = [])
    {
        $strings = self::load();
        $text = isset($strings[$key]) ? $strings[$key] : $key;
        foreach ($replace as $k => $v) {
            $text = str_replace(':' . $k, $v, $text);
        }
        return $text;
    }

    private static function file($path)
    {
        if (!is_file($path)) {
            return [];
        }
        $_LANG = [];
        include $path;
        return is_array($_LANG) ? $_LANG : [];
    }
}
