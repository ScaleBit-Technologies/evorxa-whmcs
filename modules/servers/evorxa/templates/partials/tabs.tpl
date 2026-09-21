<section class="evx-card evx-tabs-card">
    <nav class="evx-tabs" role="tablist">
        {if $evx.features.graphs}
            <button type="button" role="tab" class="evx-tab{if $evx.firstTab == 'usage'} is-active{/if}" data-tab="usage"><i class="fas fa-chart-area" aria-hidden="true"></i>{$evx.t.tab_usage|escape}</button>
        {/if}
        {if $evx.features.ddos}
            <button type="button" role="tab" class="evx-tab{if $evx.firstTab == 'ddos'} is-active{/if}" data-tab="ddos"><i class="fas fa-shield-alt" aria-hidden="true"></i>{$evx.t.tab_ddos|escape}</button>
        {/if}
        {if $evx.features.firewall}
            <button type="button" role="tab" class="evx-tab{if $evx.firstTab == 'firewall'} is-active{/if}" data-tab="firewall"><i class="fas fa-fire-alt" aria-hidden="true"></i>{$evx.t.tab_firewall|escape}</button>
        {/if}
        {if $evx.features.reinstall}
            <button type="button" role="tab" class="evx-tab{if $evx.firstTab == 'reinstall'} is-active{/if}" data-tab="reinstall"><i class="fas fa-sync-alt" aria-hidden="true"></i>{$evx.t.tab_reinstall|escape}</button>
        {/if}
    </nav>

    {if $evx.features.graphs}
    <div class="evx-panel{if $evx.firstTab == 'usage'} is-active{/if}" data-panel="usage" role="tabpanel">
        <div class="evx-panel-head">
            <p class="evx-muted">{$evx.t.usage_intro|escape}</p>
            <div class="evx-seg" data-evx="usage-period">
                <button type="button" data-period="1h" class="is-active">{$evx.t.period_1h|escape}</button>
                <button type="button" data-period="6h">{$evx.t.period_6h|escape}</button>
                <button type="button" data-period="24h">{$evx.t.period_24h|escape}</button>
            </div>
        </div>
        <div class="evx-stats">
            <div class="evx-stat"><div class="evx-stat-label">{$evx.t.stat_cpu|escape}</div><div class="evx-stat-value" data-evx="stat-cpu">&ndash;</div><div class="evx-bar"><span data-evx="bar-cpu"></span></div></div>
            <div class="evx-stat"><div class="evx-stat-label">{$evx.t.stat_memory|escape}</div><div class="evx-stat-value" data-evx="stat-mem">&ndash;</div><div class="evx-bar"><span data-evx="bar-mem"></span></div><div class="evx-stat-sub" data-evx="stat-mem-text"></div></div>
            <div class="evx-stat"><div class="evx-stat-label">{$evx.t.stat_disk|escape}</div><div class="evx-stat-value" data-evx="stat-disk">&ndash;</div><div class="evx-bar"><span data-evx="bar-disk"></span></div><div class="evx-stat-sub" data-evx="stat-disk-text"></div></div>
            <div class="evx-stat"><div class="evx-stat-label">{$evx.t.stat_traffic|escape}</div><div class="evx-stat-value evx-stat-small"><span>&darr; <span data-evx="stat-rx">&ndash;</span></span> <span>&uarr; <span data-evx="stat-tx">&ndash;</span></span></div><div class="evx-stat-sub">{$evx.t.stat_traffic_hint|escape}</div></div>
        </div>
        <div class="evx-charts">
            <figure class="evx-chart"><figcaption>{$evx.t.chart_cpu|escape}</figcaption><div class="evx-chart-box" data-chart="cpu"></div></figure>
            <figure class="evx-chart"><figcaption>{$evx.t.chart_memory|escape}</figcaption><div class="evx-chart-box" data-chart="mem"></div></figure>
            <figure class="evx-chart evx-chart-wide"><figcaption>{$evx.t.chart_network|escape}</figcaption><div class="evx-chart-box" data-chart="net"></div></figure>
        </div>
    </div>
    {/if}

    {if $evx.features.ddos}
    <div class="evx-panel{if $evx.firstTab == 'ddos'} is-active{/if}" data-panel="ddos" role="tabpanel">
        <div class="evx-banner evx-banner-ok">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <div><strong>{$evx.t.ddos_title|escape}</strong><div>{$evx.t.ddos_intro|escape}</div></div>
        </div>
        <div data-evx="ddos-unsupported" class="evx-empty" hidden>{$evx.t.ddos_unsupported|escape}</div>
        <div data-evx="ddos-body">
            <div class="evx-panel-head">
                <h5>{$evx.t.ddos_traffic|escape}</h5>
                <div class="evx-seg" data-evx="ddos-period">
                    <button type="button" data-period="live" class="is-active">{$evx.t.period_live|escape}</button>
                    <button type="button" data-period="1h">{$evx.t.period_1h|escape}</button>
                    <button type="button" data-period="1d">{$evx.t.period_1d|escape}</button>
                    <button type="button" data-period="1w">{$evx.t.period_1w|escape}</button>
                </div>
            </div>
            <div class="evx-chart"><div class="evx-chart-box" data-chart="ddos"></div></div>

            <h5 class="evx-h5">{$evx.t.ddos_attacks|escape}</h5>
            <div class="evx-table-wrap">
                <table class="evx-table">
                    <thead><tr><th>{$evx.t.ddos_started|escape}</th><th>{$evx.t.ddos_duration|escape}</th><th>{$evx.t.ddos_vectors|escape}</th><th>{$evx.t.ddos_peak|escape}</th></tr></thead>
                    <tbody data-evx="ddos-incidents"><tr><td colspan="4" class="evx-empty">{$evx.t.loading|escape}</td></tr></tbody>
                </table>
            </div>

            <h5 class="evx-h5">{$evx.t.ddos_alerts|escape}</h5>
            <form class="evx-form" data-evx="ddos-form" autocomplete="off">
                <label class="evx-switch">
                    <input type="checkbox" name="email" value="1">
                    <span class="evx-switch-ui" aria-hidden="true"></span>
                    <span>{$evx.t.ddos_email|escape}</span>
                </label>
                <button type="submit" class="btn btn-primary">{$evx.t.save|escape}</button>
            </form>
        </div>
    </div>
    {/if}

    {if $evx.features.firewall}
    <div class="evx-panel{if $evx.firstTab == 'firewall'} is-active{/if}" data-panel="firewall" role="tabpanel">
        <div class="evx-banner">
            <i class="fas fa-fire-alt" aria-hidden="true"></i>
            <div><strong>{$evx.t.fw_title|escape} <span class="evx-tag" data-evx="fw-state"></span></strong><div>{$evx.t.fw_intro|escape}</div></div>
        </div>
        <div data-evx="fw-unavailable" class="evx-empty" hidden>{$evx.t.fw_unavailable|escape}</div>
        <div data-evx="fw-body" hidden>
            <form class="evx-form evx-policy" data-evx="fw-policy">
                <label class="evx-switch evx-policy-switch">
                    <input type="checkbox" name="enabled" value="1">
                    <span class="evx-switch-ui" aria-hidden="true"></span>
                    <span><strong>{$evx.t.fw_enabled|escape}</strong><span class="evx-sub">{$evx.t.fw_enabled_hint|escape}</span></span>
                </label>
                <div class="evx-form-row">
                    <label class="evx-label" for="evx-fw-in">{$evx.t.fw_default_in|escape}</label>
                    <select id="evx-fw-in" name="in" class="form-control"><option value="allow">{$evx.t.fw_allow_all|escape}</option><option value="block">{$evx.t.fw_block_all|escape}</option></select>
                </div>
                <div class="evx-form-row">
                    <label class="evx-label" for="evx-fw-out">{$evx.t.fw_default_out|escape}</label>
                    <select id="evx-fw-out" name="out" class="form-control"><option value="allow">{$evx.t.fw_allow_all|escape}</option><option value="block">{$evx.t.fw_block_all|escape}</option></select>
                </div>
                <button type="submit" class="btn btn-default">{$evx.t.fw_save_policy|escape}</button>
            </form>

            <h5 class="evx-h5">{$evx.t.fw_rules|escape}</h5>
            <div class="evx-table-wrap">
                <table class="evx-table">
                    <thead><tr><th>{$evx.t.fw_action|escape}</th><th>{$evx.t.fw_direction|escape}</th><th>{$evx.t.fw_protocol|escape}</th><th>{$evx.t.fw_port|escape}</th><th>{$evx.t.fw_source|escape}</th><th></th></tr></thead>
                    <tbody data-evx="fw-rules"></tbody>
                </table>
            </div>

            <h5 class="evx-h5">{$evx.t.fw_add|escape}</h5>
            <div class="evx-presets">
                <span class="evx-muted">{$evx.t.fw_quick|escape}</span>
                <button type="button" class="evx-chip" data-preset="allow,tcp,22">SSH 22</button>
                <button type="button" class="evx-chip" data-preset="allow,tcp,80">HTTP 80</button>
                <button type="button" class="evx-chip" data-preset="allow,tcp,443">HTTPS 443</button>
                <button type="button" class="evx-chip" data-preset="allow,tcp,3389">RDP 3389</button>
            </div>
            <form class="evx-form evx-rule-form" data-evx="fw-add" autocomplete="off">
                <select name="rule_action" class="form-control" aria-label="{$evx.t.fw_action|escape}"><option value="allow">{$evx.t.fw_allow|escape}</option><option value="block">{$evx.t.fw_block|escape}</option></select>
                <select name="direction" class="form-control" aria-label="{$evx.t.fw_direction|escape}"><option value="in">{$evx.t.fw_in|escape}</option><option value="out">{$evx.t.fw_out|escape}</option></select>
                <select name="protocol" class="form-control" aria-label="{$evx.t.fw_protocol|escape}"><option value="tcp">TCP</option><option value="udp">UDP</option><option value="any">{$evx.t.fw_any|escape}</option></select>
                <input type="text" name="port" class="form-control" inputmode="numeric" placeholder="{$evx.t.fw_port_ph|escape}" aria-label="{$evx.t.fw_port|escape}">
                <input type="text" name="source" class="form-control" placeholder="{$evx.t.fw_source_ph|escape}" aria-label="{$evx.t.fw_source|escape}">
                <button type="submit" class="btn btn-primary">{$evx.t.fw_add_btn|escape}</button>
            </form>
        </div>
    </div>
    {/if}

    {if $evx.features.reinstall}
    <div class="evx-panel{if $evx.firstTab == 'reinstall'} is-active{/if}" data-panel="reinstall" role="tabpanel">
        <div class="evx-banner evx-banner-warn">
            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
            <div><strong>{$evx.t.reinstall_warn_title|escape}</strong><div>{$evx.t.reinstall_warn|escape}</div></div>
        </div>
        <div class="evx-seg evx-seg-wide" data-evx="reinstall-mode" hidden>
            <button type="button" data-mode="os" class="is-active">{$evx.t.reinstall_os|escape}</button>
            <button type="button" data-mode="app">{$evx.t.reinstall_app|escape}</button>
        </div>
        <div class="evx-picker" data-evx="os-list"><div class="evx-empty">{$evx.t.loading|escape}</div></div>
        <div class="evx-picker evx-apps" data-evx="app-list" hidden></div>
        <form class="evx-form" data-evx="reinstall-form">
            <label class="evx-check">
                <input type="checkbox" name="confirm" value="1">
                <span>{$evx.t.reinstall_confirm|escape} <strong class="evx-mono">{$evx.server.hostname|escape}</strong></span>
            </label>
            <button type="submit" class="btn btn-danger" disabled>{$evx.t.reinstall_btn|escape}</button>
        </form>
    </div>
    {/if}
</section>
