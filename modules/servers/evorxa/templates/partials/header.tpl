<section class="evx-card evx-hero">
    <div class="evx-hero-main">
        {if $evx.server.osLogo}
            <span class="evx-os evx-os-img" aria-hidden="true"><img src="{$evx.server.osLogo|escape}" alt="" loading="lazy"></span>
        {else}
            <span class="evx-os evx-os-{$evx.server.osFamily}" aria-hidden="true">{$evx.server.osLetter}</span>
        {/if}
        <div class="evx-hero-text">
            <div class="evx-hero-title">
                <h3 class="evx-hostname" title="{$evx.server.hostname|escape}">{if $evx.server.hostname}{$evx.server.hostname|escape}{else}{$product|escape}{/if}</h3>
                <span class="evx-pill evx-tone-{$evx.status.tone}" data-evx="status">
                    <span class="evx-dot"></span><span data-evx="status-label">{$evx.status.label|escape}</span>
                </span>
            </div>
            <div class="evx-hero-meta">
                {if $evx.server.ip}
                    <button type="button" class="evx-chip" data-copy="{$evx.server.ip|escape}" title="{$evx.t.copy|escape}">
                        <i class="far fa-copy" aria-hidden="true"></i><span class="evx-mono" data-evx="ip">{$evx.server.ip|escape}</span>
                    </button>
                {/if}
                {if $evx.server.os}<span class="evx-meta"><i class="fas fa-compact-disc" aria-hidden="true"></i>{$evx.server.os|escape}</span>{/if}
                {if $evx.server.location}<span class="evx-meta">{if $evx.server.flag}<span class="evx-flag">{$evx.server.flag}</span>{else}<i class="fas fa-map-marker-alt" aria-hidden="true"></i>{/if}{$evx.server.location|escape}</span>{/if}
            </div>
        </div>
    </div>
    {if $evx.state == 'active' && $evx.features.power}
        <div class="evx-power" role="group" aria-label="{$evx.t.power_controls|escape}">
            <button type="button" class="btn btn-default evx-btn" data-power="boot" data-when="off">
                <i class="fas fa-play" aria-hidden="true"></i><span>{$evx.t.power_boot|escape}</span>
            </button>
            <button type="button" class="btn btn-default evx-btn" data-power="restart" data-when="on">
                <i class="fas fa-redo" aria-hidden="true"></i><span>{$evx.t.power_restart|escape}</span>
            </button>
            <button type="button" class="btn btn-default evx-btn" data-power="shutdown" data-when="on">
                <i class="fas fa-stop" aria-hidden="true"></i><span>{$evx.t.power_shutdown|escape}</span>
            </button>
            <button type="button" class="btn btn-default evx-btn evx-btn-quiet" data-power="poweroff" data-when="on" title="{$evx.t.power_poweroff_hint|escape}">
                <i class="fas fa-power-off" aria-hidden="true"></i><span>{$evx.t.power_poweroff|escape}</span>
            </button>
        </div>
    {/if}
</section>
