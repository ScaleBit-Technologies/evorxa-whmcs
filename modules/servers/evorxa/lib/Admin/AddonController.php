<?php

namespace WHMCS\Module\Server\Evorxa\Admin;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Evorxa\Api\ApiException;
use WHMCS\Module\Server\Evorxa\Api\Client;
use WHMCS\Module\Server\Evorxa\Cache;
use WHMCS\Module\Server\Evorxa\Catalog;
use WHMCS\Module\Server\Evorxa\Importer;
use WHMCS\Module\Server\Evorxa\Mailer;
use WHMCS\Module\Server\Evorxa\Repo;
use WHMCS\Module\Server\Evorxa\Schema;
use WHMCS\Module\Server\Evorxa\Settings;
use WHMCS\Module\Server\Evorxa\Util;
use WHMCS\Module\Server\Evorxa\View;
use WHMCS\Module\Server\Evorxa\Watcher;

/**
 * Addons > Evorxa Manager: dashboard, settings, plan importer, servers and activity log.
 */
class AddonController
{
    const PAGES = ['dashboard' => 'Dashboard', 'plans' => 'Plans & Import', 'servers' => 'Servers', 'log' => 'Activity Log', 'settings' => 'Settings', 'install' => 'Install / Export'];

    private $link;
    private $flash = [];
    private $consoleUrl = 'https://console.evorxa.com';

    public static function handle(array $vars)
    {
        return (new self($vars))->dispatch();
    }

    private function __construct(array $vars)
    {
        $this->link = isset($vars['modulelink']) ? $vars['modulelink'] : 'addonmodules.php?module=evorxa_manager';
        Schema::ensure();
    }

    private function dispatch()
    {
        $page = isset($_GET['page']) && isset(self::PAGES[$_GET['page']]) ? $_GET['page'] : 'dashboard';
        $data = [];
        try {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['evx_action'])) {
                check_token('WHMCS.admin.default');
                $data = $this->post((string) $_POST['evx_action'], $page);
            }
            $data = array_merge($this->$page(), $data);
        } catch (ApiException $e) {
            $this->flash('danger', $e->friendly());
        } catch (\Throwable $e) {
            $this->flash('danger', $e->getMessage());
        }
        $assets = \WHMCS\Module\Server\Evorxa\ViewModel::assetsUrl();
        return View::render('admin/addon/layout.tpl', array_merge($data, [
            'brandLogo' => $assets . '/brand/evorxa-logo.png',
            'brandIcon' => $assets . '/brand/evorxa-icon.png',
            'version' => Client::VERSION,
            'consoleUrl' => $this->consoleUrl,
            'icons' => ['dashboard' => 'fa-tachometer-alt', 'plans' => 'fa-layer-group', 'servers' => 'fa-server', 'log' => 'fa-history', 'settings' => 'fa-cog', 'install' => 'fa-download'],
            'page' => $page,
            'pages' => self::PAGES,
            'link' => $this->link,
            'csrf' => generate_token('plain'),
            'flash' => $this->flash,
            'tplDir' => View::templatesDir(),
        ]));
    }

    private function flash($type, $message)
    {
        $this->flash[] = ['type' => $type, 'message' => $message];
    }

    private function api()
    {
        return Client::forDefaultServer();
    }

    // ------------------------------------------------------------------ POST actions

    private function post($action, $page)
    {
        switch ($action) {
            case 'save_settings':
                $this->saveSettings();
                return [];
            case 'import':
                return ['importReport' => $this->import()];
            case 'sync_stock':
                $changed = Watcher::syncStock();
                $this->flash('success', 'Stock synced from Evorxa (' . $changed . ' product(s) updated).');
                return [];
            case 'check_balance':
                Cache::delete('dash:account');
                $cents = Watcher::checkBalance();
                $this->flash('success', 'Wallet checked: ' . Util::formatCents($cents) . '. An alert is emailed when it is below your threshold.');
                return [];
            case 'download_package':
                Repo::log(null, 'export', true, 'Module package downloaded (' . \WHMCS\Module\Server\Evorxa\Packager::filename() . ')');
                \WHMCS\Module\Server\Evorxa\Packager::stream(); // exits on success
                return [];
            case 'run_sync':
                Cache::delete('gate:sync');
                Cache::delete('dash:account');
                Watcher::tick();
                $this->flash('success', 'Background sync ran.');
                return [];
        }
        return [];
    }

    private function saveSettings()
    {
        $project = (int) (isset($_POST['project_id']) ? $_POST['project_id'] : 0);
        $owned = (new Catalog($this->api()))->ownedProjects();
        if ($project && !isset($owned[$project])) {
            throw new \RuntimeException('That Evorxa project is not one you own.');
        }
        $suffix = strtolower(trim((string) (isset($_POST['hostname_suffix']) ? $_POST['hostname_suffix'] : ''), " .\t"));
        if ($suffix !== '' && !Util::validFqdn($suffix)) {
            throw new \RuntimeException('The hostname suffix must be a domain such as example.com.');
        }
        Settings::set('project_id', $project ?: '');
        Settings::set('hostname_suffix', $suffix);
        Settings::set('low_balance', max(0, (float) (isset($_POST['low_balance']) ? $_POST['low_balance'] : 0)));
        Settings::set('provision_timeout', max(10, min(240, (int) (isset($_POST['provision_timeout']) ? $_POST['provision_timeout'] : 30))));
        foreach (Settings::FEATURES as $feature) {
            Settings::set('feature_' . $feature, empty($_POST['feature_' . $feature]) ? '0' : '1');
        }
        $this->flash('success', 'Settings saved.');
    }

    private function import()
    {
        $packages = array_map('intval', (array) (isset($_POST['packages']) ? $_POST['packages'] : []));
        if (!$packages) {
            throw new \RuntimeException('Tick at least one plan to import.');
        }
        $report = (new Importer($this->api()))->run([
            'packages' => $packages,
            'group_id' => (int) (isset($_POST['group_id']) ? $_POST['group_id'] : 0),
            'new_group' => isset($_POST['new_group']) ? (string) $_POST['new_group'] : '',
            'markup' => (float) (isset($_POST['markup']) ? $_POST['markup'] : 40),
            'rounding' => isset($_POST['rounding']) ? (string) $_POST['rounding'] : '99',
            'cycles' => (array) (isset($_POST['cycles']) ? $_POST['cycles'] : []),
            'hidden' => !empty($_POST['hidden']),
            'options' => !empty($_POST['options']),
        ]);
        $created = count(array_filter($report['rows'], function ($r) {
            return $r['status'] === 'created';
        }));
        $this->flash($created ? 'success' : 'info', $created . ' product(s) created in product group #' . $report['group'] . '.');
        return $report;
    }

    // ------------------------------------------------------------------ pages

    private function dashboard()
    {
        $server = Client::defaultServer();
        $data = ['server' => $server ? ['id' => $server->id, 'name' => $server->name] : null, 'account' => null];
        $checks = [];
        $checks[] = ['ok' => (bool) $server, 'label' => 'Evorxa server added', 'hint' => 'Setup > Servers > Add New Server, module "Evorxa Cloud", API token in the Password field.', 'url' => 'configservers.php'];

        if ($server) {
            try {
                // Cached for a minute so the dashboard opens instantly; "Check wallet" / "Sync now" refresh it.
                $data['account'] = Cache::remember('dash:account', 60, function () {
                    $api = $this->api();
                    $me = (new Catalog($api))->me(true);
                    $balance = $api->get('wallet/balance');
                    $cents = isset($balance['balance_cents']) ? (int) $balance['balance_cents'] : 0;
                    $renewal = isset($me['user']['next_renewal']) && is_array($me['user']['next_renewal']) ? $me['user']['next_renewal'] : null;
                    return [
                        'email' => isset($me['user']['email']) ? $me['user']['email'] : '',
                        'balance' => Util::formatCents($cents),
                        'low' => $cents < (float) Settings::get('low_balance') * 100,
                        'renewalAmount' => $renewal ? Util::formatCents($renewal['amount_cents']) : 'Nothing due',
                        'renewalWhen' => $renewal ? 'Due ' . Util::date($renewal['at']) . ' · ' . (int) $renewal['instances'] . ' server(s) on the account' : '',
                        'autoRenew' => !isset($me['user']['auto_renew']) || !empty($me['user']['auto_renew']),
                    ];
                });
            } catch (ApiException $e) {
                $this->flash('danger', $e->friendly());
            }
        }
        $projects = [];
        try {
            $projects = $server ? (new Catalog($this->api()))->ownedProjects() : [];
        } catch (\Throwable $e) {
        }
        $pid = Settings::projectId();
        $checks[] = ['ok' => $pid > 0, 'label' => 'Project for new servers: ' . ($pid ? (isset($projects[$pid]) ? $projects[$pid] . ' (#' . $pid . ')' : '#' . $pid) : 'not chosen'), 'hint' => 'Tip: create a dedicated project (e.g. "Resale") in the Evorxa dashboard so client servers stay apart from your own.', 'url' => $this->link . '&page=settings'];
        $suffix = Settings::get('hostname_suffix');
        $checks[] = ['ok' => $suffix !== '', 'label' => 'Hostname suffix: ' . ($suffix ?: 'not set'), 'hint' => 'Client servers get hostnames like vps123.' . ($suffix ?: 'example.com') . ' (also used as reverse DNS).', 'url' => $this->link . '&page=settings'];
        $products = Capsule::table('tblproducts')->where('servertype', 'evorxa')->where('retired', 0)->get(['id', 'name', 'hidden']);
        $visible = 0;
        foreach ($products as $p) {
            $visible += $p->hidden ? 0 : 1;
        }
        $checks[] = ['ok' => count($products) > 0, 'label' => count($products) . ' Evorxa product(s), ' . $visible . ' visible in the store', 'hint' => 'Import plans with your markup, then review and unhide them in Setup > Products/Services.', 'url' => $this->link . '&page=plans'];
        $lastCron = strtotime((string) Capsule::table('tblconfiguration')->where('setting', 'lastCronInvocationTime')->value('value'));
        $cronOk = $lastCron && time() - $lastCron < 15 * 60;
        $checks[] = ['ok' => $cronOk, 'label' => 'WHMCS cron ran ' . ($lastCron ? self::ago($lastCron) : 'never'), 'hint' => 'Run the WHMCS cron every 5 minutes (*/5 * * * *) so new servers are finalised and emailed promptly and renewals are checked.', 'url' => 'systemcronstatus.php'];
        $data['checks'] = $checks;
        $done = count(array_filter($checks, function ($c) {
            return $c['ok'];
        }));
        $data['progress'] = ['done' => $done, 'total' => count($checks), 'pct' => (int) round(100 * $done / max(1, count($checks)))];
        $data['finance'] = $this->finance();

        $counts = [];
        foreach (Capsule::table(Schema::SERVICES)->select('state', Capsule::raw('COUNT(*) AS c'))->groupBy('state')->get() as $row) {
            $counts[$row->state] = (int) $row->c;
        }
        $data['counts'] = $counts;
        $data['attention'] = $this->attention();
        $data['attentionCount'] = count($data['attention']);
        $data['recent'] = $this->logRows(Capsule::table(Schema::LOG)->orderBy('id', 'desc')->limit(10)->get());
        return $data;
    }

    /**
     * Monthly economics of active Evorxa services, in the default WHMCS currency:
     * what clients pay (normalised per month) versus what Evorxa charges for their plans.
     */
    private function finance()
    {
        $default = Capsule::table('tblcurrencies')->where('default', 1)->first();
        if (!$default) {
            return null;
        }
        $rates = Capsule::table('tblcurrencies')->pluck('rate', 'id')->all();
        $usdRate = (float) Capsule::table('tblcurrencies')->where('code', 'USD')->value('rate');
        $months = ['monthly' => 1, 'quarterly' => 3, 'semi-annually' => 6, 'annually' => 12, 'biennially' => 24, 'triennially' => 36];
        $rows = Capsule::table(Schema::SERVICES . ' as e')
            ->join('tblhosting as h', 'h.id', '=', 'e.service_id')
            ->join('tblclients as c', 'c.id', '=', 'h.userid')
            ->where('e.state', 'active')->where('h.domainstatus', 'Active')
            ->get(['h.amount', 'h.billingcycle', 'c.currency', 'e.package_id', 'e.upstream_cycle']);
        if (!count($rows)) {
            return ['count' => 0];
        }
        try {
            $plans = (new Catalog())->plans();
        } catch (\Throwable $e) {
            $plans = [];
        }
        $revenue = 0.0;
        $cost = 0.0;
        $costKnown = true;
        foreach ($rows as $r) {
            $m = isset($months[strtolower($r->billingcycle)]) ? $months[strtolower($r->billingcycle)] : 0;
            $rate = isset($rates[$r->currency]) && (float) $rates[$r->currency] > 0 ? (float) $rates[$r->currency] : 1;
            if ($m) {
                $revenue += ((float) $r->amount / $m) / $rate;
            }
            $cycle = $r->upstream_cycle ?: 'monthly';
            if (isset($plans[$r->package_id]['pricing'][$cycle]['per_month'])) {
                $usd = (float) $plans[$r->package_id]['pricing'][$cycle]['per_month'];
                $cost += $usdRate > 0 ? $usd / $usdRate : $usd;
            } else {
                $costKnown = false;
            }
        }
        $fmt = function ($v) use ($default) {
            return $default->prefix . number_format($v, 2) . $default->suffix;
        };
        return [
            'count' => count($rows),
            'revenue' => $fmt($revenue),
            'cost' => $fmt($cost),
            'margin' => $fmt($revenue - $cost),
            'marginPct' => $revenue > 0 ? (int) round(100 * ($revenue - $cost) / $revenue) : 0,
            'negative' => $revenue < $cost,
            'costKnown' => $costKnown,
        ];
    }

    private function attention()
    {
        $rows = Capsule::table(Schema::SERVICES)->whereIn('state', ['failed', 'unknown', 'creating'])->orderBy('updated_at', 'desc')->limit(20)->get();
        $list = [];
        foreach ($rows as $row) {
            $list[] = ['service' => $row->service_id, 'state' => $row->state, 'error' => (string) $row->error, 'when' => $row->updated_at];
        }
        return $list;
    }

    private function settings()
    {
        $projects = [];
        $error = '';
        try {
            $projects = (new Catalog($this->api()))->ownedProjects();
        } catch (\Throwable $e) {
            $error = $e instanceof ApiException ? $e->friendly() : $e->getMessage();
        }
        $values = Settings::all();
        $features = [];
        $labels = [
            'power' => 'Start, restart, shut down, force off',
            'password' => 'Reset root password',
            'reinstall' => 'Reinstall operating system',
            'apps' => 'Reinstall with one-click apps',
            'graphs' => 'Usage graphs (CPU, memory, disk, network)',
            'ddos' => 'DDoS protection view and attack email alerts',
            'firewall' => 'Network firewall rules',
            'rdns' => 'Edit reverse DNS (rDNS) of the server\'s IP addresses',
        ];
        foreach (Settings::FEATURES as $f) {
            $features[] = ['key' => $f, 'label' => $labels[$f], 'on' => $values['feature_' . $f] === '1'];
        }
        $template = Capsule::table('tblemailtemplates')->where('name', Mailer::TEMPLATE)->where('language', '')->value('id');
        return [
            'projects' => $projects,
            'projectsError' => $error,
            'values' => $values,
            'features' => $features,
            'emailTemplateId' => $template,
            'emailTemplate' => Mailer::TEMPLATE,
        ];
    }

    private function plans()
    {
        $plans = (new Catalog($this->api()))->plans(true);
        $linked = [];
        foreach (Capsule::table('tblproducts')->where('servertype', 'evorxa')->get(['id', 'name', 'gid', 'hidden', 'retired', 'configoption1']) as $p) {
            $monthly = Capsule::table('tblpricing')->where('type', 'product')->where('relid', $p->id)
                ->where('currency', (int) Capsule::table('tblcurrencies')->where('default', 1)->value('id'))->value('monthly');
            $linked[(int) $p->configoption1][] = [
                'id' => $p->id, 'name' => $p->name, 'hidden' => (bool) $p->hidden, 'retired' => (bool) $p->retired,
                'monthly' => $monthly !== null && (float) $monthly > 0 ? (float) $monthly : null,
            ];
        }
        $rows = [];
        foreach ($plans as $id => $plan) {
            $cost = isset($plan['pricing']['monthly']['total']) ? (float) $plan['pricing']['monthly']['total'] : null;
            $products = isset($linked[$id]) ? $linked[$id] : [];
            foreach ($products as &$product) {
                $product['margin'] = $cost && $product['monthly'] ? round(100 * ($product['monthly'] - $cost) / $product['monthly']) . '%' : '';
                $product['monthlyText'] = $product['monthly'] ? number_format($product['monthly'], 2) : '';
            }
            unset($product);
            $rows[] = [
                'id' => $id,
                'category' => preg_replace('/\s+VMs?$/i', '', $plan['category_name']),
                'name' => Importer::productName($plan),
                'specs' => Catalog::specLine($plan),
                'stock' => (int) (isset($plan['stock']) ? $plan['stock'] : 0),
                'monthly' => $cost !== null ? number_format($cost, 2) : '-',
                'semi' => isset($plan['pricing']['6months']['total']) ? number_format((float) $plan['pricing']['6months']['total'], 2) : '-',
                'yearly' => isset($plan['pricing']['yearly']['total']) ? number_format((float) $plan['pricing']['yearly']['total'], 2) : '-',
                'products' => $products,
                'imported' => (bool) $products,
            ];
        }
        $groups = [];
        foreach (Capsule::table('tblproductgroups')->orderBy('order')->get(['id', 'name', 'hidden']) as $g) {
            $groups[] = ['id' => $g->id, 'name' => $g->name . ($g->hidden ? ' (hidden)' : '')];
        }
        return ['plans' => $rows, 'groups' => $groups, 'defaultGroup' => (int) Capsule::table('tblproductgroups')->where('slug', 'cloud-vps')->value('id')];
    }

    private function servers()
    {
        $api = $this->api();
        $projects = (new Catalog($api))->ownedProjects();
        $projectIds = array_filter(array_unique(array_merge(
            [Settings::projectId()],
            Capsule::table(Schema::SERVICES)->whereNotNull('project_id')->distinct()->pluck('project_id')->all()
        )));
        $mapped = [];
        foreach (Capsule::table(Schema::SERVICES)->whereNotNull('instance_id')->get() as $row) {
            $mapped[$row->instance_id] = $row;
        }
        $list = [];
        $seen = [];
        foreach ($projectIds as $projectId) {
            foreach ((array) $api->get('instances', ['project_id' => (int) $projectId]) as $inst) {
                if (!is_array($inst) || !isset($inst['id']) || (int) (isset($inst['project_id']) ? $inst['project_id'] : 0) !== (int) $projectId) {
                    continue;
                }
                $seen[$inst['id']] = true;
                $row = isset($mapped[$inst['id']]) ? $mapped[$inst['id']] : null;
                $hosting = $row ? Repo::hosting($row->service_id) : null;
                $flag = '';
                if (!$row || $row->state === 'terminated') {
                    $flag = 'Not linked to an active service (still billed on Evorxa)';
                } elseif ($hosting && in_array($hosting->domainstatus, ['Terminated', 'Cancelled', 'Fraud'], true)) {
                    $flag = 'Service is ' . $hosting->domainstatus . ' in WHMCS but the server still exists';
                } elseif ($hosting && $hosting->domainstatus === 'Active' && !empty($inst['suspended_at'])) {
                    $flag = 'Suspended upstream while the service is active';
                }
                $list[] = [
                    'id' => $inst['id'],
                    'name' => isset($inst['name']) ? $inst['name'] : '',
                    'hostname' => isset($inst['hostname']) ? $inst['hostname'] : '',
                    'ip' => isset($inst['main_ip']) ? $inst['main_ip'] : '',
                    'plan' => isset($inst['plan']) ? $inst['plan'] : '',
                    'status' => isset($inst['status']) ? $inst['status'] : '',
                    'billedTill' => Util::date(isset($inst['billed_till']) ? $inst['billed_till'] : null),
                    'deletion' => Util::date(isset($inst['scheduled_deletion_at']) ? $inst['scheduled_deletion_at'] : null),
                    'project' => isset($projects[$projectId]) ? $projects[$projectId] : '#' . $projectId,
                    'service' => $row && $row->state !== 'terminated' ? (int) $row->service_id : null,
                    'serviceStatus' => $hosting ? $hosting->domainstatus : '',
                    'client' => $hosting ? (int) $hosting->userid : null,
                    'flag' => $flag,
                ];
            }
        }
        $missing = [];
        foreach ($mapped as $instanceId => $row) {
            if (!isset($seen[$instanceId]) && in_array($row->state, ['active', 'provisioning'], true)) {
                $missing[] = ['service' => (int) $row->service_id, 'instance' => $instanceId, 'project' => (int) $row->project_id];
            }
        }
        return ['servers' => $list, 'missing' => $missing, 'projectNames' => array_map(function ($id) use ($projects) {
            return isset($projects[$id]) ? $projects[$id] . ' (#' . $id . ')' : '#' . $id;
        }, array_values($projectIds))];
    }

    private function log()
    {
        $service = isset($_GET['service']) ? (int) $_GET['service'] : 0;
        $pageNo = max(1, (int) (isset($_GET['p']) ? $_GET['p'] : 1));
        $query = Capsule::table(Schema::LOG)->orderBy('id', 'desc');
        if ($service) {
            $query->where('service_id', $service);
        }
        $total = (clone $query)->count();
        $rows = $query->offset(($pageNo - 1) * 50)->limit(50)->get();
        return [
            'logs' => $this->logRows($rows),
            'filterService' => $service ?: '',
            'pageNo' => $pageNo,
            'hasNext' => $total > $pageNo * 50,
        ];
    }

    private function install()
    {
        $whmcs = (string) Capsule::table('tblconfiguration')->where('setting', 'Version')->value('value');
        $lastCron = strtotime((string) Capsule::table('tblconfiguration')->where('setting', 'lastCronInvocationTime')->value('value'));
        $checks = [
            ['label' => 'PHP 7.4 or newer', 'ok' => version_compare(PHP_VERSION, '7.4.0', '>='), 'value' => PHP_VERSION],
            ['label' => 'WHMCS 8.0 or newer', 'ok' => version_compare(preg_replace('/-.*/', '', $whmcs), '8.0.0', '>='), 'value' => $whmcs],
            ['label' => 'cURL extension', 'ok' => function_exists('curl_init'), 'value' => function_exists('curl_version') ? curl_version()['version'] : 'missing'],
            ['label' => 'zip extension (for this export)', 'ok' => class_exists('ZipArchive'), 'value' => class_exists('ZipArchive') ? 'available' : 'missing'],
            ['label' => 'Cron ran in the last 15 minutes', 'ok' => $lastCron && time() - $lastCron < 900, 'value' => $lastCron ? self::ago($lastCron) : 'never'],
            ['label' => 'Evorxa API reachable', 'ok' => (bool) Client::defaultServer(), 'value' => Client::defaultServer() ? 'server configured' : 'no server yet'],
        ];
        return [
            'package' => \WHMCS\Module\Server\Evorxa\Packager::summary(),
            'packageName' => \WHMCS\Module\Server\Evorxa\Packager::filename(),
            'requirements' => ['zip' => class_exists('ZipArchive'), 'checks' => $checks],
        ];
    }

    private function logRows($rows)
    {
        $list = [];
        foreach ($rows as $log) {
            $list[] = [
                'when' => $log->created_at,
                'service' => $log->service_id,
                'actor' => $log->actor,
                'action' => $log->action,
                'ok' => (bool) $log->ok,
                'message' => (string) $log->message,
                'amount' => $log->amount_cents === null ? '' : Util::formatCents($log->amount_cents),
            ];
        }
        return $list;
    }

    private static function ago($ts)
    {
        $d = time() - $ts;
        if ($d < 120) {
            return $d . ' seconds ago';
        }
        if ($d < 7200) {
            return floor($d / 60) . ' minutes ago';
        }
        return floor($d / 3600) . ' hours ago';
    }
}
