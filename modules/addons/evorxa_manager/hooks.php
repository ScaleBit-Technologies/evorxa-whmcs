<?php
/**
 * Evorxa Manager hooks. All of the module's hooks live here: WHMCS only loads a server
 * module's hooks.php after its settings are saved by hand, but an active addon's hooks
 * are always loaded.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Module\Server\Evorxa\Keeper;
use WHMCS\Module\Server\Evorxa\Repo;
use WHMCS\Module\Server\Evorxa\Watcher;

if (!class_exists('WHMCS\\Module\\Server\\Evorxa\\Watcher')) {
    return; // Server module files missing: stay inert.
}

/** Every cron run: adopt timed-out creates, finish builds, hourly sync + renewal keeper, stock, wallet check. */
add_hook('AfterCronJob', 1, function () {
    try {
        Watcher::tick();
    } catch (\Throwable $e) {
        logActivity('Evorxa cron error: ' . $e->getMessage());
    }
});

/**
 * End-of-period cancellation: stop the upstream renewal before Evorxa charges the next cycle
 * (it renews 5 days before the paid-until date, while WHMCS terminates on the due date).
 */
add_hook('CancellationRequest', 1, function ($vars) {
    $serviceId = isset($vars['relid']) ? (int) $vars['relid'] : 0;
    if (!$serviceId || !Repo::find($serviceId)) {
        return;
    }
    try {
        Keeper::forService($serviceId);
    } catch (\Throwable $e) {
        Repo::log($serviceId, 'cancel_request', false, $e->getMessage());
    }
});
