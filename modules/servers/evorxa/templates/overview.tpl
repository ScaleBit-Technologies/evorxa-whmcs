{*
    Evorxa Cloud - client server panel (replaces the Overview tab of the service page).

    All data comes from one variable tree, {$evx}, built by lib/ViewModel.php:
      $evx.state        pending | provisioning | active | suspended | closed | failed | none
      $evx.status       key, label, tone (ok|warn|off|busy|bad), running, busy
      $evx.server       hostname, ip, os, osFamily, osLetter, windows, username, hasPassword, location, flag, connect
      $evx.specs[]      icon, label, value
      $evx.ips[]        address, type, rdns, primary
      $evx.app          slug, name, note (or empty)
      $evx.features     power, password, reinstall, apps, graphs, ddos, firewall
      $evx.t            translated strings (lang/*.php, overridable in lang/overrides/)
    Native WHMCS product-details variables ($product, $nextduedate, ...) are available too.
    Markup is free to change; the script only relies on the data-evx / data-action / data-power hooks.
*}
<link rel="stylesheet" href="{$evx.assets}/evorxa.css?v={$evx.v}">
<div class="evx" id="evx" data-state="{$evx.state}">

    {include file="`$evx.tpl`/partials/header.tpl"}

    {if $evx.state == 'active'}
        {include file="`$evx.tpl`/partials/specs.tpl"}
        {include file="`$evx.tpl`/partials/access.tpl"}
        {if $evx.hasTabs}
            {include file="`$evx.tpl`/partials/tabs.tpl"}
        {/if}
    {elseif $evx.state == 'provisioning'}
        {include file="`$evx.tpl`/partials/pending.tpl"}
    {else}
        {include file="`$evx.tpl`/partials/notice.tpl"}
    {/if}

    {include file="`$evx.tpl`/partials/billing.tpl"}

    <div class="evx-toasts" data-evx="toasts" aria-live="polite"></div>
</div>
<script type="application/json" id="evx-boot">{$evx.bootJson}</script>
<script src="{$evx.assets}/evorxa.js?v={$evx.v}"></script>
