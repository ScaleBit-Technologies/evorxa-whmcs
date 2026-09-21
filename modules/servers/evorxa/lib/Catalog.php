<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Module\Server\Evorxa\Api\Client;

/**
 * Plans, operating systems, one-click apps and projects, cached in tbltransientdata.
 */
class Catalog
{
    private $api;

    public function __construct(Client $api = null)
    {
        $this->api = $api;
    }

    /**
     * Flattened plans keyed by package_id.
     * With a token the personalised price list is used, otherwise the public one.
     */
    public function plans($fresh = false)
    {
        $key = $this->api ? 'plans:me' : 'plans:public';
        if ($fresh) {
            Cache::delete($key);
        }
        return Cache::remember($key, 600, function () {
            $raw = $this->api
                ? $this->api->get('me/catalog/plans')
                : (new Client(''))->getPublic('catalog/plans');
            $plans = [];
            foreach ((array) $raw as $category) {
                foreach ((array) (isset($category['plans']) ? $category['plans'] : []) as $plan) {
                    if (!isset($plan['package_id'])) {
                        continue;
                    }
                    $plan['category'] = isset($category['category']) ? $category['category'] : 'standard';
                    $plan['category_name'] = isset($category['category_name']) ? $category['category_name'] : ucfirst($plan['category']);
                    $plans[(int) $plan['package_id']] = $plan;
                }
            }
            return $plans;
        });
    }

    public function plan($packageId, $fresh = false)
    {
        $plans = $this->plans($fresh);
        return isset($plans[(int) $packageId]) ? $plans[(int) $packageId] : null;
    }

    public function planBySlug($slug)
    {
        foreach ($this->plans() as $plan) {
            if (isset($plan['slug']) && $plan['slug'] === $slug) {
                return $plan;
            }
        }
        return null;
    }

    /** Price of one upstream cycle in cents, or null when the plan has no such cycle. */
    public static function cycleCents(array $plan, $cycle)
    {
        if (!isset($plan['pricing'][$cycle]['total'])) {
            return null;
        }
        return (int) round(((float) $plan['pricing'][$cycle]['total']) * 100);
    }

    /** "Budget · Nano — 1 vCPU · 2 GB RAM · 30 GB NVMe · 500 Mbps". */
    public static function planLabel(array $plan, $withPrice = true)
    {
        $category = preg_replace('/\s+VMs?$/i', '', isset($plan['category_name']) ? $plan['category_name'] : '');
        $label = trim($category . ' · ' . $plan['name']) . ' — ' . self::specLine($plan);
        if ($withPrice && isset($plan['pricing']['monthly']['total'])) {
            $label .= ' — $' . number_format((float) $plan['pricing']['monthly']['total'], 2) . '/mo';
        }
        if (isset($plan['stock']) && (int) $plan['stock'] <= 0) {
            $label .= ' (out of stock)';
        }
        return $label;
    }

    public static function specLine(array $plan)
    {
        $parts = [
            (int) $plan['vcpu'] . ' vCPU',
            (int) $plan['ram'] . ' GB RAM',
            (int) $plan['storage'] . ' GB NVMe',
        ];
        if (!empty($plan['uplink'])) {
            $parts[] = $plan['uplink'];
        }
        return implode(' · ', $parts);
    }

    /** Operating systems for one package, flattened: [{id, slug, label, family, type, eol}]. */
    public function osTemplates($packageId)
    {
        $this->requireToken();
        $plan = $this->plan($packageId);
        $group = $plan && isset($plan['hypervisor_group_id']) ? 'h' . (int) $plan['hypervisor_group_id'] : 'p' . (int) $packageId;
        return Cache::remember('os:' . $group, 3600, function () use ($packageId) {
            return self::flattenOs($this->api->get('catalog/os-templates/' . (int) $packageId));
        });
    }

    /** Reinstall choices for an existing instance (same flattened shape). */
    public function reinstallOptions($instanceId, $packageId)
    {
        $this->requireToken();
        return Cache::remember('reinstall:' . (int) $packageId, 3600, function () use ($instanceId) {
            return self::flattenOs($this->api->get('instances/' . rawurlencode($instanceId) . '/reinstall-options'));
        });
    }

    public static function flattenOs($groups)
    {
        $list = [];
        $seen = [];
        foreach ((array) $groups as $group) {
            $family = isset($group['name']) ? $group['name'] : '';
            foreach ((array) (isset($group['templates']) ? $group['templates'] : []) as $t) {
                if (!isset($t['id'])) {
                    continue;
                }
                $slug = Util::osSlug($family, $t);
                if (isset($seen[$slug])) {
                    $slug .= '-' . (int) $t['id'];
                }
                $seen[$slug] = true;
                $list[] = [
                    'id' => (int) $t['id'],
                    'slug' => $slug,
                    'label' => Util::osLabel($t),
                    'family' => Util::clean($family, 40),
                    'type' => isset($t['type']) && $t['type'] === 'windows' ? 'windows' : 'linux',
                    'eol' => Util::isEol($t),
                ];
            }
        }
        return $list;
    }

    public function osBySlug($packageId, $slug)
    {
        foreach ($this->osTemplates($packageId) as $os) {
            if ($os['slug'] === $slug) {
                return $os;
            }
        }
        return null;
    }

    /** All OS choices across hardware groups (one package per group), slug => label. */
    public function osUnion()
    {
        $this->requireToken();
        $byGroup = [];
        foreach ($this->plans() as $plan) {
            $g = isset($plan['hypervisor_group_id']) ? (int) $plan['hypervisor_group_id'] : 0;
            if (!isset($byGroup[$g])) {
                $byGroup[$g] = (int) $plan['package_id'];
            }
        }
        $union = [];
        foreach ($byGroup as $packageId) {
            foreach ($this->osTemplates($packageId) as $os) {
                $union[$os['slug']] = $os['label'];
            }
        }
        return $union;
    }

    /** Public one-click apps, minus those that need a licence key up front. */
    public function apps()
    {
        return Cache::remember('apps', 3600, function () {
            $raw = (new Client(''))->getPublic('catalog/apps');
            $apps = [];
            foreach ((array) (isset($raw['apps']) ? $raw['apps'] : []) as $app) {
                if (empty($app['slug']) || !empty($app['license_required_upfront'])) {
                    continue;
                }
                $apps[$app['slug']] = [
                    'slug' => $app['slug'],
                    'name' => Util::clean(isset($app['name']) ? $app['name'] : $app['slug'], 60),
                    'tagline' => Util::clean(isset($app['tagline']) ? $app['tagline'] : '', 120),
                    'category' => Util::clean(isset($app['category']) ? $app['category'] : '', 40),
                    'min_ram_mb' => (int) (isset($app['min_ram_mb']) ? $app['min_ram_mb'] : 0),
                    'min_storage_gb' => (int) (isset($app['min_storage_gb']) ? $app['min_storage_gb'] : 0),
                    'setup_note' => Util::clean(isset($app['setup_note']) ? $app['setup_note'] : '', 400),
                    'default_username' => Util::clean(isset($app['default_username']) ? $app['default_username'] : '', 80),
                ];
            }
            return $apps;
        });
    }

    public function appsFor(array $plan)
    {
        $fit = [];
        foreach ($this->apps() as $slug => $app) {
            if ($app['min_ram_mb'] <= (int) $plan['ram'] * 1024 && $app['min_storage_gb'] <= (int) $plan['storage']) {
                $fit[$slug] = $app;
            }
        }
        return $fit;
    }

    public function me($fresh = false)
    {
        $this->requireToken();
        if ($fresh) {
            Cache::delete('me');
        }
        return Cache::remember('me', 300, function () {
            return $this->api->get('me');
        });
    }

    /** Projects the token's user owns (only owners can create or delete servers). */
    public function ownedProjects()
    {
        $me = $this->me();
        $projects = [];
        foreach ((array) (isset($me['projects']) ? $me['projects'] : []) as $p) {
            if (isset($p['id']) && (!isset($p['current_user_role']) || $p['current_user_role'] === 'owner')) {
                $projects[(int) $p['id']] = Util::clean(isset($p['name']) ? $p['name'] : ('Project ' . $p['id']), 80);
            }
        }
        return $projects;
    }

    private function requireToken()
    {
        if (!$this->api) {
            throw new Api\ApiException('An Evorxa API token is required for this lookup.');
        }
    }
}
