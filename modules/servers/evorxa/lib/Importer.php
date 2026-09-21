<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;
use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * Creates WHMCS products from Evorxa plans: pricing with markup, Module Settings,
 * "Operating System" / "One-Click App" configurable options, hostname + SSH key fields,
 * stock control and upgrade paths. Re-running skips plans already imported into the group.
 */
class Importer
{
    const CYCLES = [
        'monthly' => 'monthly',
        'semiannually' => '6months',
        'annually' => 'yearly',
    ];

    private $api;
    private $catalog;

    public function __construct(Client $api)
    {
        $this->api = $api;
        $this->catalog = new Catalog($api);
    }

    /**
     * @param array $opts packages[], group_id, new_group, markup (%), rounding (99|whole|none),
     *                    cycles[] (monthly|semiannually|annually), hidden (bool), options (bool)
     * @return array report rows: plan, status (created|skipped|error), pid, name, prices, message
     */
    public function run(array $opts)
    {
        Schema::ensure();
        Mailer::install();
        $plans = $this->catalog->plans(true);
        $serverGroup = $this->serverGroup();
        $gid = $this->productGroup($opts);
        $markup = max(-50, min(1000, (float) $opts['markup']));
        $cycles = array_values(array_intersect(array_keys(self::CYCLES), (array) $opts['cycles']));
        if (!$cycles) {
            $cycles = ['monthly'];
        }
        $currencies = $this->currencies();
        $order = (int) Capsule::table('tblproducts')->where('gid', $gid)->max('order');
        $report = [];

        foreach ((array) $opts['packages'] as $packageId) {
            $packageId = (int) $packageId;
            if (!isset($plans[$packageId])) {
                $report[] = ['plan' => '#' . $packageId, 'status' => 'error', 'message' => 'Plan not found upstream'];
                continue;
            }
            $plan = $plans[$packageId];
            $name = self::productName($plan);
            $existing = Capsule::table('tblproducts')
                ->where('servertype', 'evorxa')->where('gid', $gid)->where('retired', 0)
                ->where('configoption1', (string) $packageId)->first();
            if ($existing) {
                $report[] = ['plan' => $name, 'status' => 'skipped', 'pid' => $existing->id, 'name' => $existing->name, 'message' => 'Already in this group'];
                continue;
            }
            try {
                $pricing = [];
                $shown = [];
                foreach ($currencies as $currency) {
                    $row = ['msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                        'monthly' => -1, 'quarterly' => -1, 'semiannually' => -1, 'annually' => -1, 'biennially' => -1, 'triennially' => -1];
                    foreach ($cycles as $cycle) {
                        $upstream = isset($plan['pricing'][self::CYCLES[$cycle]]['total']) ? (float) $plan['pricing'][self::CYCLES[$cycle]]['total'] : null;
                        if ($upstream === null) {
                            continue;
                        }
                        $row[$cycle] = self::price($upstream * (1 + $markup / 100) * $currency['factor'], $opts['rounding']);
                        if ($currency['default']) {
                            $shown[$cycle] = $currency['prefix'] . number_format($row[$cycle], 2) . $currency['suffix'];
                        }
                    }
                    $pricing[$currency['id']] = $row;
                }

                $result = localAPI('AddProduct', [
                    'type' => 'other',
                    'gid' => $gid,
                    'name' => $name,
                    'description' => self::description($plan, $this->offersWindows($packageId)),
                    'hidden' => !empty($opts['hidden']),
                    'paytype' => 'recurring',
                    'autosetup' => 'payment',
                    'module' => 'evorxa',
                    'servergroupid' => $serverGroup,
                    'welcomeemail' => 0,
                    'configoption1' => (string) $packageId,
                    'configoption2' => $this->defaultOs($packageId),
                    'configoption3' => 'match',
                    'pricing' => $pricing,
                ]);
                if (!isset($result['result']) || $result['result'] !== 'success') {
                    throw new \RuntimeException(isset($result['message']) ? $result['message'] : 'AddProduct failed');
                }
                $pid = (int) $result['pid'];
                Capsule::table('tblproducts')->where('id', $pid)->update([
                    'tagline' => Catalog::specLine($plan),
                    'short_description' => Catalog::specLine($plan),
                    'stockcontrol' => 1,
                    'qty' => max(0, (int) (isset($plan['stock']) ? $plan['stock'] : 0)),
                    'configoptionsupgrade' => 0,
                    'showdomainoptions' => 0,
                    'order' => ++$order,
                ]);
                if (!empty($opts['options'])) {
                    $this->attachOptions($pid, $plan, $currencies);
                    $this->addCustomFields($pid);
                }
                self::syncTranslations($pid);
                $report[] = ['plan' => $name, 'status' => 'created', 'pid' => $pid, 'name' => $name, 'prices' => $shown, 'message' => 'Stock ' . (int) $plan['stock']];
            } catch (\Throwable $e) {
                $report[] = ['plan' => $name, 'status' => 'error', 'message' => $e->getMessage()];
            }
        }
        $this->linkUpgrades($plans);
        Repo::log(null, 'import', true, 'Imported ' . count(array_filter($report, function ($r) {
            return $r['status'] === 'created';
        })) . ' plan(s) into product group #' . $gid . ' with ' . $markup . '% markup');
        return ['group' => $gid, 'rows' => $report];
    }

    public static function productName(array $plan)
    {
        $category = trim(preg_replace('/\s+VMs?$/i', '', isset($plan['category_name']) ? $plan['category_name'] : ''));
        return trim($category . ' ' . $plan['name']);
    }

    /** Customer-facing price rounding: 99 => next x.99, whole => next integer, none => cents. */
    public static function price($value, $rounding)
    {
        if ($rounding === '99') {
            return round(ceil($value + 0.01) - 0.01, 2);
        }
        if ($rounding === 'whole') {
            return (float) ceil($value);
        }
        return round($value, 2);
    }

    public static function description(array $plan, $windows = false)
    {
        $items = [
            (int) $plan['vcpu'] . ' vCPU cores',
            (int) $plan['ram'] . ' GB RAM',
            (int) $plan['storage'] . ' GB NVMe SSD storage',
        ];
        if (!empty($plan['uplink'])) {
            $items[] = htmlspecialchars($plan['uplink'], ENT_QUOTES, 'UTF-8') . ' network port';
        }
        $items[] = 'Always-on DDoS protection';
        $items[] = 'Full root access, ready in minutes';
        $items[] = $windows ? 'Linux, Windows or one-click apps' : 'Linux or one-click apps';
        return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    }

    /**
     * WHMCS keeps storefront text in tbldynamic_translations too, and those rows win over
     * tblproducts. Copy the product's own text into existing rows for the system language
     * so the store never shows stale copies (other languages are left alone).
     */
    public static function syncTranslations($pid)
    {
        if (!Capsule::schema()->hasTable('tbldynamic_translations')) {
            return 0;
        }
        $language = (string) Capsule::table('tblconfiguration')->where('setting', 'Language')->value('value') ?: 'english';
        $product = Capsule::table('tblproducts')->where('id', (int) $pid)->first(['name', 'description', 'short_description', 'tagline']);
        if (!$product) {
            return 0;
        }
        $updated = 0;
        foreach (['name', 'description', 'short_description', 'tagline'] as $field) {
            $updated += Capsule::table('tbldynamic_translations')
                ->where('related_type', 'product.{id}.' . $field)
                ->where('related_id', (int) $pid)
                ->where('language', $language)
                ->update(['translation' => (string) $product->$field, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        return $updated;
    }

    public function offersWindows($packageId)
    {
        foreach ($this->catalog->osTemplates($packageId) as $os) {
            if ($os['type'] === 'windows' && !$os['eol']) {
                return true;
            }
        }
        return false;
    }

    private function defaultOs($packageId)
    {
        $first = '';
        foreach ($this->catalog->osTemplates($packageId) as $os) {
            if ($os['slug'] === 'ubuntu-24.04') {
                return $os['slug'];
            }
            if (!$first && !$os['eol'] && $os['type'] === 'linux') {
                $first = $os['slug'];
            }
        }
        return $first;
    }

    /** Evorxa server group, created (with every enabled Evorxa server) when missing. */
    private function serverGroup()
    {
        $serverIds = Capsule::table('tblservers')->where('type', 'evorxa')->where('disabled', 0)->pluck('id')->all();
        if (!$serverIds) {
            throw new \RuntimeException('Add your Evorxa server first (Setup > Servers).');
        }
        $existing = Capsule::table('tblservergroupsrel')->whereIn('serverid', $serverIds)->value('groupid');
        if ($existing) {
            return (int) $existing;
        }
        $gid = Capsule::table('tblservergroups')->insertGetId(['name' => 'Evorxa', 'filltype' => 1]);
        foreach ($serverIds as $sid) {
            Capsule::table('tblservergroupsrel')->insert(['groupid' => $gid, 'serverid' => $sid]);
        }
        return $gid;
    }

    private function productGroup(array $opts)
    {
        if (!empty($opts['group_id']) && Capsule::table('tblproductgroups')->where('id', (int) $opts['group_id'])->count()) {
            return (int) $opts['group_id'];
        }
        $name = trim(isset($opts['new_group']) ? (string) $opts['new_group'] : '') ?: 'Cloud VPS';
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-') ?: 'cloud-vps';
        $base = $slug;
        for ($i = 2; Capsule::table('tblproductgroups')->where('slug', $slug)->count(); $i++) {
            $slug = $base . '-' . $i;
        }
        $now = date('Y-m-d H:i:s');
        return Capsule::table('tblproductgroups')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'headline' => 'High-performance NVMe cloud servers',
            'tagline' => 'Deployed in minutes, with DDoS protection included',
            'orderfrmtpl' => '',
            'disabledgateways' => '',
            'hidden' => 1,
            'order' => (int) Capsule::table('tblproductgroups')->max('order') + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** WHMCS currencies with a conversion factor from USD. */
    private function currencies()
    {
        $rows = Capsule::table('tblcurrencies')->get();
        $usd = null;
        foreach ($rows as $row) {
            if (strtoupper($row->code) === 'USD') {
                $usd = $row;
            }
        }
        $rates = null;
        $list = [];
        foreach ($rows as $row) {
            if ($usd) {
                $factor = (float) $usd->rate > 0 ? (float) $row->rate / (float) $usd->rate : 1;
            } else {
                if ($rates === null) {
                    $raw = (new Client(''))->getPublic('catalog/exchange-rates');
                    $rates = isset($raw['rates']) ? $raw['rates'] : [];
                }
                if (!isset($rates[strtoupper($row->code)])) {
                    continue;
                }
                $factor = (float) $rates[strtoupper($row->code)];
            }
            $list[] = [
                'id' => (int) $row->id,
                'factor' => $factor,
                'default' => (bool) $row->default,
                'prefix' => (string) $row->prefix,
                'suffix' => (string) $row->suffix,
            ];
        }
        if (!$list) {
            throw new \RuntimeException('No WHMCS currency can be priced (add USD or a currency Evorxa publishes rates for).');
        }
        return $list;
    }

    /** Shared "Operating System" (+ "One-Click App") option group for every product with the same choices. */
    private function attachOptions($pid, array $plan, array $currencies)
    {
        $os = [];
        foreach ($this->catalog->osTemplates($plan['package_id']) as $item) {
            if (!$item['eol']) {
                $os[$item['slug']] = $item['label'];
            }
        }
        uksort($os, function ($a, $b) {
            $rank = function ($slug) {
                return $slug === 'ubuntu-24.04' ? 0 : (strpos($slug, 'ubuntu') === 0 ? 1 : (strpos($slug, 'debian') === 0 ? 2 : (strpos($slug, 'windows') === 0 ? 4 : 3)));
            };
            return $rank($a) === $rank($b) ? strnatcmp($b, $a) : $rank($a) - $rank($b);
        });
        $apps = [];
        foreach ($this->catalog->appsFor($plan) as $slug => $app) {
            $apps[$slug] = $app['name'] . ' - ' . $app['tagline'];
        }
        $key = substr(md5(json_encode([$os, $apps])), 0, 12);
        $group = Capsule::table('tblproductconfiggroups')->where('description', 'evx:' . $key)->first();
        if (!$group) {
            $gid = Capsule::table('tblproductconfiggroups')->insertGetId([
                'name' => 'Evorxa: ' . count($os) . ' operating systems, ' . count($apps) . ' apps (' . $key . ')',
                'description' => 'evx:' . $key,
            ]);
            $this->addOption($gid, 'os|Operating System', 1, $os, $currencies);
            if ($apps) {
                $this->addOption($gid, 'app|One-Click App (optional, replaces the OS)', 2, ['none' => 'None - use the operating system above'] + $apps, $currencies);
            }
        } else {
            $gid = $group->id;
        }
        if (!Capsule::table('tblproductconfiglinks')->where('gid', $gid)->where('pid', $pid)->count()) {
            Capsule::table('tblproductconfiglinks')->insert(['gid' => $gid, 'pid' => $pid]);
        }
    }

    private function addOption($gid, $name, $order, array $choices, array $currencies)
    {
        $optionId = Capsule::table('tblproductconfigoptions')->insertGetId([
            'gid' => $gid, 'optionname' => $name, 'optiontype' => 1, 'qtyminimum' => 0, 'qtymaximum' => 0, 'order' => $order, 'hidden' => 0,
        ]);
        $i = 0;
        foreach ($choices as $value => $label) {
            $subId = Capsule::table('tblproductconfigoptionssub')->insertGetId([
                'configid' => $optionId, 'optionname' => $value . '|' . $label, 'sortorder' => $i++, 'hidden' => 0,
            ]);
            // Every sub-option needs a (zero) price row per currency, or the order form hides it.
            foreach ($currencies as $currency) {
                Capsule::table('tblpricing')->insert([
                    'type' => 'configoptions', 'currency' => $currency['id'], 'relid' => $subId,
                    'msetupfee' => 0, 'qsetupfee' => 0, 'ssetupfee' => 0, 'asetupfee' => 0, 'bsetupfee' => 0, 'tsetupfee' => 0,
                    'monthly' => 0, 'quarterly' => 0, 'semiannually' => 0, 'annually' => 0, 'biennially' => 0, 'triennially' => 0,
                ]);
            }
        }
    }

    private function addCustomFields($pid)
    {
        $now = date('Y-m-d H:i:s');
        $fields = [
            [
                'fieldname' => 'hostname|Server Hostname',
                'fieldtype' => 'text',
                'description' => 'Optional. A name like web1, or a full hostname like server.example.com.',
                'regexpr' => '/^$|^[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?(\.[A-Za-z0-9]([A-Za-z0-9-]{0,61}[A-Za-z0-9])?)*\.?$/',
                'sortorder' => 1,
            ],
            [
                'fieldname' => 'sshkey|SSH Public Key',
                'fieldtype' => 'textarea',
                'description' => 'Optional. Paste your OpenSSH public key (ssh-ed25519 or ssh-rsa) to log in without a password.',
                'regexpr' => '/^\s*$|^\s*(ssh-(rsa|ed25519|dss)|ecdsa-sha2-nistp(256|384|521)|sk-(ssh-ed25519|ecdsa-sha2-nistp256)@openssh\.com)\s+[A-Za-z0-9+\/]+={0,3}(\s+[^\r\n]*)?\s*$/',
                'sortorder' => 2,
            ],
        ];
        foreach ($fields as $field) {
            $exists = Capsule::table('tblcustomfields')->where('type', 'product')->where('relid', $pid)->where('fieldname', $field['fieldname'])->count();
            if ($exists) {
                continue;
            }
            Capsule::table('tblcustomfields')->insert($field + [
                'type' => 'product', 'relid' => $pid, 'fieldoptions' => '', 'adminonly' => '', 'required' => '',
                'showorder' => 'on', 'showinvoice' => '', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    /** Upgrades only to bigger plans of the same family (Evorxa cannot shrink disks). */
    private function linkUpgrades(array $plans)
    {
        $products = Capsule::table('tblproducts')->where('servertype', 'evorxa')->get(['id', 'configoption1']);
        foreach ($products as $from) {
            $a = isset($plans[(int) $from->configoption1]) ? $plans[(int) $from->configoption1] : null;
            if (!$a) {
                continue;
            }
            foreach ($products as $to) {
                $b = isset($plans[(int) $to->configoption1]) ? $plans[(int) $to->configoption1] : null;
                if (!$b || $from->id === $to->id || $a['category'] !== $b['category']) {
                    continue;
                }
                $bigger = $b['ram'] >= $a['ram'] && $b['storage'] >= $a['storage'] && $b['vcpu'] >= $a['vcpu']
                    && ($b['ram'] > $a['ram'] || $b['storage'] > $a['storage'] || $b['vcpu'] > $a['vcpu']);
                if ($bigger && !Capsule::table('tblproduct_upgrade_products')->where('product_id', $from->id)->where('upgrade_product_id', $to->id)->count()) {
                    Capsule::table('tblproduct_upgrade_products')->insert([
                        'product_id' => $from->id, 'upgrade_product_id' => $to->id,
                        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }
}
