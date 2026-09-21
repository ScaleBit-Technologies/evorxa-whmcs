<?php
/**
 * Evorxa Manager - companion addon for the "Evorxa Cloud" server module.
 *
 * Dashboard, settings, plan importer, server reconciliation and the activity log.
 * Also hosts the module's hooks (see hooks.php). Deactivating it keeps all data.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Module\Server\Evorxa\Admin\AddonController;
use WHMCS\Module\Server\Evorxa\Mailer;
use WHMCS\Module\Server\Evorxa\Schema;
use WHMCS\Module\Server\Evorxa\Settings;

function evorxa_manager_config()
{
    return [
        'name' => 'Evorxa Manager',
        'description' => 'Resell Evorxa servers: settings, plan importer, wallet alerts, server reconciliation and activity log. '
            . 'Requires the "Evorxa Cloud" server module (modules/servers/evorxa).',
        'version' => '1.0.0',
        'author' => 'ScaleBit Technologies',
        'language' => 'english',
        'fields' => [],
    ];
}

function evorxa_manager_lib_ok()
{
    return class_exists('WHMCS\\Module\\Server\\Evorxa\\Schema');
}

function evorxa_manager_activate()
{
    if (!evorxa_manager_lib_ok()) {
        return ['status' => 'error', 'description' => 'Upload modules/servers/evorxa first: the addon uses its library.'];
    }
    try {
        Schema::ensure();
        Settings::installPrefix();
        Mailer::install();
    } catch (\Throwable $e) {
        return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
    }
    return [
        'status' => 'success',
        'description' => 'Evorxa Manager is active. Open Addons > Evorxa Manager to finish the setup.',
    ];
}

function evorxa_manager_deactivate()
{
    // Tables are kept on purpose: losing the service <-> server mapping would orphan paid servers.
    return [
        'status' => 'success',
        'description' => 'Deactivated. All Evorxa data is kept; background sync and alerts stop until you reactivate.',
    ];
}

function evorxa_manager_upgrade($vars)
{
    if (evorxa_manager_lib_ok()) {
        Schema::ensure();
        Mailer::install();
    }
}

function evorxa_manager_output($vars)
{
    if (!evorxa_manager_lib_ok()) {
        echo '<div class="alert alert-danger">The Evorxa Cloud server module is missing. Upload <code>modules/servers/evorxa</code>.</div>';
        return;
    }
    try {
        echo AddonController::handle($vars);
    } catch (\Throwable $e) {
        logActivity('Evorxa Manager page error: ' . $e->getMessage());
        echo '<div class="alert alert-danger"><strong>Evorxa Manager could not load this page.</strong> '
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>';
    }
}
