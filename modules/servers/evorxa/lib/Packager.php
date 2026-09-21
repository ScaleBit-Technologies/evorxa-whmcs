<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * Builds an upload-ready zip of the installed module (server module + Manager addon),
 * so it can be moved to any other WHMCS: extract into the WHMCS root, then follow INSTALL.txt.
 */
class Packager
{
    /** Paths inside the module folders that are site-specific and never exported. */
    const EXCLUDE = ['lang/overrides/'];

    public static function folders()
    {
        $root = defined('ROOTDIR') ? ROOTDIR : dirname(__DIR__, 4);
        return [
            'modules/servers/evorxa' => dirname(__DIR__),
            'modules/addons/evorxa_manager' => $root . '/modules/addons/evorxa_manager',
        ];
    }

    public static function filename()
    {
        return 'evorxa-whmcs-' . Client::VERSION . '.zip';
    }

    /** File count and size of what would be exported. */
    public static function summary()
    {
        $files = 0;
        $bytes = 0;
        foreach (self::files() as $path) {
            $files++;
            $bytes += (int) @filesize($path[0]);
        }
        return ['files' => $files, 'size' => Util::formatBytes($bytes), 'missing' => self::missingFolders()];
    }

    public static function missingFolders()
    {
        $missing = [];
        foreach (self::folders() as $name => $dir) {
            if (!is_dir($dir)) {
                $missing[] = $name;
            }
        }
        return $missing;
    }

    /** [absolute path, path inside the zip] for every exported file. */
    private static function files()
    {
        $list = [];
        foreach (self::folders() as $prefix => $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
                foreach (self::EXCLUDE as $skip) {
                    if (strpos($relative, $skip) === 0) {
                        continue 2;
                    }
                }
                $list[] = [$file->getPathname(), $prefix . '/' . $relative];
            }
        }
        usort($list, function ($a, $b) {
            return strcmp($a[1], $b[1]);
        });
        return $list;
    }

    /** Create the zip in the temp dir and return its path. */
    public static function build()
    {
        if (!class_exists('ZipArchive')) {
            throw new \RuntimeException('The PHP "zip" extension (ZipArchive) is required to build the package.');
        }
        if ($missing = self::missingFolders()) {
            throw new \RuntimeException('Missing module folder(s): ' . implode(', ', $missing));
        }
        $path = tempnam(sys_get_temp_dir(), 'evx');
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the zip file in ' . sys_get_temp_dir() . '.');
        }
        foreach (self::files() as $file) {
            $zip->addFile($file[0], $file[1]);
        }
        $zip->addFromString('INSTALL.txt', self::instructions());
        $zip->close();
        return $path;
    }

    /** Send the zip to the browser and stop (called before WHMCS prints the admin page). */
    public static function stream()
    {
        $path = self::build();
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (headers_sent()) {
            @unlink($path);
            throw new \RuntimeException('The download could not start because the page had already begun loading. Please try again.');
        }
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . self::filename() . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        @unlink($path);
        exit;
    }

    public static function instructions()
    {
        return "Evorxa Cloud for WHMCS " . Client::VERSION . "\n"
            . "========================================\n\n"
            . "Requirements: WHMCS 8.0+, PHP 7.4-8.3 with cURL, WHMCS cron every 5 minutes.\n\n"
            . "1. Extract this zip into your WHMCS root folder (it adds modules/servers/evorxa\n"
            . "   and modules/addons/evorxa_manager; nothing else is touched).\n"
            . "2. WHMCS admin > Setup > Addon Modules: activate \"Evorxa Manager\" and give your\n"
            . "   admin roles access.\n"
            . "3. Evorxa console > API tokens: create a token with instances:read, instances:write,\n"
            . "   analytics:read, shield:read, shield:write, wallet:read.\n"
            . "4. Setup > Products/Services > Servers > Add New Server: module \"Evorxa Cloud\",\n"
            . "   hostname api.evorxa.com, paste the token into Password, click Test Connection.\n"
            . "5. Addons > Evorxa Manager > Settings: choose the Evorxa project for client servers\n"
            . "   (a dedicated one is best) and your hostname suffix (e.g. example.com).\n"
            . "6. Addons > Evorxa Manager > Plans & Import: pick plans, set your markup, import.\n"
            . "   Products are created hidden; review them, then unhide.\n"
            . "7. Make sure the WHMCS cron runs every 5 minutes:\n"
            . "   */5 * * * * php -q /path/to/whmcs/crons/cron.php\n\n"
            . "Updating: extract a newer zip over the old files. Settings, data and\n"
            . "lang/overrides are kept.\n";
    }
}
