<div class="evx-grid">
    <section class="evx-card">
        <header class="evx-card-head">
            <h4><i class="fas fa-terminal" aria-hidden="true"></i>{$evx.t.access_title|escape}</h4>
        </header>
        <div class="evx-field">
            <div class="evx-label">{if $evx.server.windows}{$evx.t.connect_rdp|escape}{else}{$evx.t.connect_ssh|escape}{/if}</div>
            <div class="evx-value">
                <code class="evx-mono evx-grow">{$evx.server.connect|escape}</code>
                <button type="button" class="evx-icon-btn" data-copy="{$evx.server.connect|escape}" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="evx-field">
            <div class="evx-label">{$evx.t.username|escape}</div>
            <div class="evx-value">
                <code class="evx-mono evx-grow">{$evx.server.username|escape}</code>
                <button type="button" class="evx-icon-btn" data-copy="{$evx.server.username|escape}" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button>
            </div>
        </div>
        <div class="evx-field">
            <div class="evx-label">{$evx.t.password|escape}</div>
            <div class="evx-value">
                <code class="evx-mono evx-grow" data-evx="password">&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;</code>
                {if $evx.server.hasPassword}
                    <button type="button" class="evx-icon-btn" data-action="password-toggle" title="{$evx.t.show|escape}"><i class="far fa-eye" aria-hidden="true"></i></button>
                    <button type="button" class="evx-icon-btn" data-action="password-copy" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button>
                {/if}
            </div>
            {if $evx.features.password}
                <button type="button" class="evx-link" data-action="password-reset"><i class="fas fa-key" aria-hidden="true"></i>{$evx.t.password_reset|escape}</button>
            {/if}
        </div>
    </section>

    <section class="evx-card">
        <header class="evx-card-head">
            <h4><i class="fas fa-network-wired" aria-hidden="true"></i>{$evx.t.network_title|escape}</h4>
        </header>
        {if $evx.ips}
            <ul class="evx-ips">
                {foreach $evx.ips as $ip}
                    <li>
                        <div class="evx-value">
                            <code class="evx-mono evx-grow">{$ip.address|escape}</code>
                            <span class="evx-tag">{$ip.type|escape}</span>
                            {if $ip.primary}<span class="evx-tag evx-tag-accent">{$evx.t.primary|escape}</span>{/if}
                            <button type="button" class="evx-icon-btn" data-copy="{$ip.address|escape}" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button>
                        </div>
                        {if $ip.rdns}<div class="evx-sub">{$evx.t.rdns|escape}: <span class="evx-mono">{$ip.rdns|escape}</span></div>{/if}
                    </li>
                {/foreach}
            </ul>
        {else}
            <p class="evx-muted">{$evx.t.no_ips|escape}</p>
        {/if}
        <div class="evx-protect">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <span>{$evx.t.ddos_always_on|escape}</span>
        </div>
    </section>
</div>

{if $evx.app}
<section class="evx-card evx-app" data-evx="app">
    <header class="evx-card-head">
        <h4>{if $evx.app.logo}<img class="evx-app-logo" src="{$evx.app.logo|escape}" alt="" aria-hidden="true">{else}<i class="fas fa-cube" aria-hidden="true"></i>{/if}{$evx.app.name|escape}</h4>
        <button type="button" class="btn btn-default btn-sm" data-action="app-reveal">{$evx.t.app_show_login|escape}</button>
    </header>
    <p class="evx-muted">{$evx.t.app_intro|escape}</p>
    <div class="evx-app-details" data-evx="app-details" hidden>
        <div class="evx-field">
            <div class="evx-label">{$evx.t.app_url|escape}</div>
            <div class="evx-value"><a class="evx-mono evx-grow" data-evx="app-url" href="#" target="_blank" rel="noopener noreferrer"></a></div>
        </div>
        <div class="evx-field">
            <div class="evx-label">{$evx.t.username|escape}</div>
            <div class="evx-value"><code class="evx-mono evx-grow" data-evx="app-user"></code><button type="button" class="evx-icon-btn" data-copy-from="app-user" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button></div>
        </div>
        <div class="evx-field">
            <div class="evx-label">{$evx.t.password|escape}</div>
            <div class="evx-value"><code class="evx-mono evx-grow" data-evx="app-pass"></code><button type="button" class="evx-icon-btn" data-copy-from="app-pass" title="{$evx.t.copy|escape}"><i class="far fa-copy" aria-hidden="true"></i></button></div>
        </div>
        <p class="evx-note" data-evx="app-note" hidden></p>
    </div>
</section>
{/if}
