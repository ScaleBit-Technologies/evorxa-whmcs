<?php
/**
 * Evorxa Cloud - WHMCS provisioning module for reselling Evorxa VPS / VDS servers.
 *
 * Entry points only; all logic lives in lib/ (namespace WHMCS\Module\Server\Evorxa,
 * autoloaded by WHMCS). Pair it with the "Evorxa Manager" addon, which runs the
 * background sync, alerts, plan importer and settings.
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

use WHMCS\Module\Server\Evorxa\Admin\ServiceTab;
use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;
use WHMCS\Module\Server\Evorxa\Catalog;
use WHMCS\Module\Server\Evorxa\ClientActions;
use WHMCS\Module\Server\Evorxa\Provisioner;
use WHMCS\Module\Server\Evorxa\Repo;
use WHMCS\Module\Server\Evorxa\ViewModel;

function evorxa_MetaData()
{
    return [
        'DisplayName' => 'Evorxa Cloud',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultSSLPort' => '443',
        'ServiceSingleSignOnLabel' => false,
        'AdminSingleSignOnLabel' => false,
    ];
}

function evorxa_ConfigOptions()
{
    return [
        'Plan' => [
            'Type' => 'text',
            'Size' => '25',
            'Loader' => 'evorxa_LoaderPlans',
            'SimpleMode' => true,
            'Description' => 'Evorxa plan this product sells.',
        ],
        'Default OS' => [
            'Type' => 'text',
            'Size' => '25',
            'Loader' => 'evorxa_LoaderOs',
            'SimpleMode' => true,
            'Description' => 'Used when the order has no "Operating System" option.',
        ],
        'Upstream Billing Term' => [
            'Type' => 'dropdown',
            'Options' => [
                'match' => 'Match the client\'s term (cheapest upstream price)',
                'monthly' => 'Always monthly upstream',
            ],
            'Default' => 'match',
            'Description' => 'How Evorxa bills your wallet for this server.',
        ],
    ];
}

/** Module Settings "Plan" dropdown. Falls back to the public catalog before a server is assigned. */
function evorxa_LoaderPlans(array $params)
{
    $api = !empty($params['serverpassword']) ? Client::fromParams($params) : null;
    try {
        $plans = (new Catalog($api))->plans(true);
    } catch (ApiException $e) {
        throw new \Exception('Could not load Evorxa plans: ' . $e->friendly());
    }
    $options = [];
    foreach ($plans as $id => $plan) {
        $options[$id] = Catalog::planLabel($plan);
    }
    return $options;
}

/** Module Settings "Default OS" dropdown: every image across Evorxa's hardware groups. */
function evorxa_LoaderOs(array $params)
{
    if (empty($params['serverpassword'])) {
        throw new \Exception('Choose the Server Group that contains your Evorxa server, save, then reopen this tab.');
    }
    try {
        return (new Catalog(Client::fromParams($params)))->osUnion();
    } catch (ApiException $e) {
        throw new \Exception('Could not load operating systems: ' . $e->friendly());
    }
}

function evorxa_TestConnection(array $params)
{
    try {
        $api = Client::fromParams($params);
        $me = $api->get('me');
        $email = isset($me['user']['email']) ? $me['user']['email'] : 'unknown';
        $missing = [];
        $tokenId = $api->tokenId();
        if ($tokenId) {
            foreach ((array) $api->get('me/api-tokens') as $token) {
                if ((int) (isset($token['id']) ? $token['id'] : 0) !== $tokenId) {
                    continue;
                }
                $abilities = isset($token['abilities']) ? (array) $token['abilities'] : [];
                if (!in_array('*', $abilities, true)) {
                    foreach (['instances:read', 'instances:write', 'analytics:read', 'shield:read', 'shield:write', 'wallet:read'] as $need) {
                        if (!in_array($need, $abilities, true)) {
                            $missing[] = $need;
                        }
                    }
                }
            }
        }
        if ($missing) {
            return ['success' => false, 'error' => 'Connected as ' . $email . ', but the token is missing: ' . implode(', ', $missing) . '.'];
        }
        return ['success' => true, 'error' => ''];
    } catch (ApiException $e) {
        return ['success' => false, 'error' => $e->friendly()];
    } catch (\Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/** Run a Provisioner action and turn exceptions into an admin-readable error string. */
function evorxa_run(array $params, callable $fn)
{
    try {
        return $fn(new Provisioner($params));
    } catch (ApiException $e) {
        Repo::log(isset($params['serviceid']) ? $params['serviceid'] : null, 'error', false, $e->friendly());
        return $e->friendly();
    } catch (\Throwable $e) {
        Repo::log(isset($params['serviceid']) ? $params['serviceid'] : null, 'error', false, $e->getMessage());
        logModuleCall('evorxa', 'exception', '', $e->getMessage() . "\n" . $e->getTraceAsString());
        return 'Evorxa module error: ' . $e->getMessage();
    }
}

function evorxa_CreateAccount(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->create();
    });
}

function evorxa_SuspendAccount(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->suspend();
    });
}

function evorxa_UnsuspendAccount(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->unsuspend();
    });
}

function evorxa_TerminateAccount(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->terminate();
    });
}

function evorxa_ChangePackage(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->changePackage();
    });
}

function evorxa_Renew(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->renew();
    });
}

function evorxa_AdminCustomButtonArray()
{
    return [
        'Boot' => 'boot',
        'Restart' => 'restart',
        'Shut Down' => 'shutdown',
        'Power Off' => 'poweroff',
        'Reset Root Password' => 'resetpw',
        'Sync Now' => 'sync',
        'Reactivate Upstream' => 'reactivate',
        'Cancel Upstream Deletion' => 'canceldeletion',
    ];
}

function evorxa_boot(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->power('boot');
    });
}

function evorxa_restart(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->power('restart');
    });
}

function evorxa_shutdown(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->power('shutdown');
    });
}

function evorxa_poweroff(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->power('poweroff');
    });
}

function evorxa_resetpw(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->resetPassword();
    });
}

function evorxa_sync(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->sync();
    });
}

function evorxa_reactivate(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->reactivate();
    });
}

function evorxa_canceldeletion(array $params)
{
    return evorxa_run($params, function (Provisioner $p) {
        return $p->cancelDeletion();
    });
}

function evorxa_AdminServicesTabFields(array $params)
{
    try {
        return ServiceTab::fields($params);
    } catch (\Throwable $e) {
        return ['Evorxa' => '<div class="alert alert-danger">' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</div>'];
    }
}

function evorxa_AdminServicesTabFieldsSave(array $params)
{
    try {
        ServiceTab::save($params);
    } catch (\Throwable $e) {
        Repo::log($params['serviceid'], 'link', false, $e->getMessage());
    }
}

function evorxa_ClientArea(array $params)
{
    try {
        return [
            'tabOverviewReplacementTemplate' => 'templates/overview.tpl',
            'templateVariables' => ['evx' => ViewModel::build($params)],
        ];
    } catch (\Throwable $e) {
        logModuleCall('evorxa', 'ClientArea', '', $e->getMessage());
        return [
            'tabOverviewReplacementTemplate' => 'templates/error.tpl',
            'templateVariables' => ['evx' => ViewModel::minimal($params)],
        ];
    }
}

/** JSON endpoint behind the client panel: clientarea.php?action=productdetails&id=N&modop=custom&a=api */
function evorxa_ClientAreaAllowedFunctions()
{
    return ['API' => 'api'];
}

function evorxa_api(array $params)
{
    ClientActions::respond($params);
}
