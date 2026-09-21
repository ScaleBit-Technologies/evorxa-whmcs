<?php

namespace WHMCS\Module\Server\Evorxa;

use WHMCS\Database\Capsule;

/**
 * Visual OS / app picker on the order form ("configure product" page).
 *
 * Progressive enhancement: the order form keeps its normal dropdowns (created by the
 * importer as "os|Operating System" and "app|One-Click App ..."); assets/orderform.js
 * hides them and drives them from logo cards, so totals, validation and any order-form
 * template keep working. Without JavaScript the plain dropdowns remain.
 */
class OrderForm
{
    /** Footer HTML for the configure page of an Evorxa product, or '' for anything else. */
    public static function footer(array $vars)
    {
        if ((isset($vars['templatefile']) ? $vars['templatefile'] : '') !== 'configureproduct') {
            return '';
        }
        $pid = isset($vars['productinfo']['pid']) ? (int) $vars['productinfo']['pid'] : 0;
        if (!$pid || Capsule::table('tblproducts')->where('id', $pid)->value('servertype') !== 'evorxa') {
            return '';
        }
        $map = self::map($pid);
        if (!$map['options']) {
            return '';
        }
        $assets = ViewModel::assetsUrl();
        $dir = View::templatesDir() . '/assets/';
        $v = substr(md5(@filemtime($dir . 'orderform.js') . '|' . @filemtime($dir . 'orderform.css')), 0, 8);
        return '<link rel="stylesheet" href="' . $assets . '/orderform.css?v=' . $v . '">'
            . '<script type="application/json" id="evx-of-map">'
            . json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
            . '</script>'
            . '<script src="' . $assets . '/orderform.js?v=' . $v . '" defer></script>';
    }

    /** Option id => type + choices (sub-option id => display data) for one product. */
    public static function map($pid)
    {
        $options = [];
        $groupIds = Capsule::table('tblproductconfiglinks')->where('pid', (int) $pid)->pluck('gid')->all();
        $rows = $groupIds ? Capsule::table('tblproductconfigoptions')->whereIn('gid', $groupIds)->get(['id', 'optionname']) : [];
        foreach ($rows as $option) {
            $key = strtolower(strtok((string) $option->optionname, '|'));
            if ($key !== 'os' && $key !== 'app') {
                continue;
            }
            $choices = [];
            $subs = Capsule::table('tblproductconfigoptionssub')->where('configid', $option->id)->where('hidden', 0)
                ->orderBy('sortorder')->get(['id', 'optionname']);
            foreach ($subs as $sub) {
                $parts = explode('|', (string) $sub->optionname, 2);
                $value = strtolower(trim($parts[0]));
                $label = isset($parts[1]) ? trim($parts[1]) : $parts[0];
                $choices[$sub->id] = $key === 'os' ? self::osChoice($value, $label) : self::appChoice($value, $label);
            }
            $options[$option->id] = ['type' => $key, 'choices' => $choices];
        }
        $t = Lang::load();
        $pick = function ($k, $fallback) use ($t) {
            return isset($t[$k]) ? $t[$k] : $fallback;
        };
        return [
            'options' => $options,
            't' => [
                'version' => $pick('of_version', 'Version'),
                'none' => $pick('of_none', 'No app'),
                'noneSub' => $pick('of_none_sub', 'Just the operating system'),
                'appNote' => $pick('of_app_note', 'The selected app installs its own system, so the operating system choice is not used.'),
                'title' => $pick('of_title', 'Operating system or app'),
                'modeOs' => $pick('of_mode_os', 'Operating system'),
                'modeApp' => $pick('of_mode_app', 'One-click app'),
                'versions' => $pick('of_versions', ':n versions'),
                'appHint' => $pick('of_app_hint', 'Pick an app. It comes with its own operating system, installed and ready to use.'),
            ],
        ];
    }

    /** ubuntu-24.04 + "Ubuntu Server 24.04 LTS (Noble Numbat)" => family Ubuntu, title "24.04 LTS", sub "Noble Numbat". */
    private static function osChoice($slug, $label)
    {
        $family = strtok($slug, '-');
        $name = preg_split('/\s+/', $label);
        $familyName = $name ? $name[0] : ucfirst($family);
        $sub = '';
        if (preg_match('/\(([^)]*)\)/', $label, $m)) {
            $sub = $m[1];
        }
        $title = trim(preg_replace('/\([^)]*\)/', '', $label));
        $title = trim(preg_replace('/^' . preg_quote($familyName, '/') . '(\s+Server)?\s*/i', '', $title));
        return [
            'value' => $slug,
            'family' => $family,
            'familyName' => Util::clean($familyName, 40),
            'title' => Util::clean($title !== '' ? $title : $label, 60),
            'sub' => Util::clean($sub, 60),
            'logo' => ViewModel::logo('os', ViewModel::osFamily($familyName)),
        ];
    }

    /** "n8n - Workflow automation" => title n8n, sub Workflow automation. */
    private static function appChoice($slug, $label)
    {
        $parts = explode(' - ', $label, 2);
        return [
            'value' => $slug,
            'title' => Util::clean(trim($parts[0]), 60),
            'sub' => Util::clean(isset($parts[1]) ? trim($parts[1]) : '', 80),
            'logo' => $slug === 'none' ? '' : ViewModel::logo('apps', $slug),
        ];
    }
}
