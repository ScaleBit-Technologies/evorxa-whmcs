<div class="evxm-grid">
    <div class="evxm-kpi">
        <span class="evxm-kpi-ic{if $account && $account.low} warn{/if}"><i class="fas fa-wallet"></i></span>
        <div>
            <small>Evorxa wallet</small>
            <strong>{if $account}{$account.balance|escape}{else}&ndash;{/if}</strong>
            <div class="evxm-sub">{if $account && $account.low}<span class="evxm-badge evxm-warn">below your alert level</span> <a href="{$consoleUrl}" target="_blank" rel="noopener">Top up</a>{else}Used to pay for servers and renewals{/if}</div>
        </div>
    </div>
    <div class="evxm-kpi">
        <span class="evxm-kpi-ic ink"><i class="far fa-calendar-alt"></i></span>
        <div>
            <small>Next Evorxa renewal</small>
            <strong>{if $account}{$account.renewalAmount|escape}{else}&ndash;{/if}</strong>
            <div class="evxm-sub">{if $account}{$account.renewalWhen|escape}{if $account.renewalWhen} · {/if}auto-renew {if $account.autoRenew}on{else}off{/if}{/if}</div>
        </div>
    </div>
    <div class="evxm-kpi">
        <span class="evxm-kpi-ic ok"><i class="fas fa-server"></i></span>
        <div>
            <small>Client servers</small>
            <strong>{if $counts.active}{$counts.active}{else}0{/if} <span style="font-size:13px;font-weight:600;color:#6b7280">active</span></strong>
            <div class="evxm-sub">{if $counts.provisioning}<span class="evxm-badge evxm-busy">{$counts.provisioning} building</span> {/if}{if $counts.terminated}{$counts.terminated} closed{/if}</div>
        </div>
    </div>
    <div class="evxm-kpi">
        <span class="evxm-kpi-ic{if !$attentionCount} ok{/if}"><i class="fas {if $attentionCount}fa-exclamation-triangle{else}fa-check{/if}"></i></span>
        <div>
            <small>Needs attention</small>
            <strong>{$attentionCount}</strong>
            <div class="evxm-sub">{if $attentionCount}Failed or unconfirmed orders{else}All good{/if}</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7">
        <div class="evxm-card">
            <h3><i class="fas fa-rocket" style="color:#e2445c"></i> Setup <span class="evxm-right evxm-muted">{$progress.done} of {$progress.total} complete</span></h3>
            <div class="evxm-progress"><span style="width:{$progress.pct}%"></span></div>
            {foreach $checks as $c}
                <div class="evxm-check">
                    <span class="ic {if $c.ok}ok{else}no{/if}"><i class="fas {if $c.ok}fa-check{else}fa-exclamation{/if}"></i></span>
                    <div class="txt">
                        <div>{$c.label|escape}</div>
                        {if !$c.ok}<div class="evxm-muted">{$c.hint|escape}</div>{/if}
                    </div>
                    <a class="btn btn-{if $c.ok}link{else}default{/if} btn-sm" href="{$c.url}">{if $c.ok}View{else}Fix{/if}</a>
                </div>
            {/foreach}
        </div>

        <div class="evxm-card">
            <h3><i class="fas fa-history"></i> Recent activity <a class="evxm-right" href="{$link}&amp;page=log">View all</a></h3>
            {include file="`$tplDir`/admin/addon/logtable.tpl" logs=$recent}
        </div>
    </div>

    <div class="col-lg-5">
        <div class="evxm-card">
            <h3><i class="fas fa-chart-line"></i> Monthly economics</h3>
            {if $finance && $finance.count}
                <div class="evxm-money">
                    <div><small>Clients pay</small><strong>{$finance.revenue|escape}</strong></div>
                    <div><small>Evorxa cost</small><strong>{$finance.cost|escape}</strong></div>
                    <div><small>Margin</small><strong class="{if $finance.negative}neg{else}pos{/if}">{$finance.margin|escape}</strong> <span class="evxm-muted">{$finance.marginPct}%</span></div>
                </div>
                <p class="evxm-muted" style="margin:10px 0 0">Across {$finance.count} active server(s), per month.{if !$finance.costKnown} Some plans are no longer in the Evorxa catalogue, so the cost is incomplete.{/if}</p>
            {else}
                <p class="evxm-muted" style="margin:0">Appears once you have active client servers.</p>
            {/if}
        </div>

        <div class="evxm-card">
            <h3><i class="fas fa-bell"></i> Needs attention</h3>
            {if $attention}
                <div class="evxm-table" style="margin:0">
                <table class="table table-condensed">
                    {foreach $attention as $a}
                        <tr>
                            <td><a href="clientsservices.php?id={$a.service}">Service #{$a.service}</a></td>
                            <td><span class="evxm-badge evxm-bad">{$a.state|escape}</span></td>
                            <td class="evxm-muted">{$a.error|escape}</td>
                        </tr>
                    {/foreach}
                </table>
                </div>
            {else}
                <div class="evxm-empty"><i class="fas fa-check-circle"></i>Nothing needs your attention.</div>
            {/if}
        </div>

        {if $account}
        <div class="evxm-card">
            <h3><i class="fas fa-plug"></i> Connection</h3>
            <p style="margin:0 0 4px">Connected as <strong>{$account.email|escape}</strong></p>
            <p class="evxm-muted" style="margin:0">{if $server}Server <a href="configservers.php?action=manage&amp;id={$server.id}">{$server.name|escape}</a>. {/if}{if $account.autoRenew}Evorxa renews each server from your wallet 5 days before it expires; the module reactivates or stops servers to match what clients paid.{else}The module extends each server only after its client has paid.{/if}</p>
            <div class="evxm-actions">
                <form method="post" action="{$link}&amp;page=dashboard">
                    <input type="hidden" name="token" value="{$csrf}"><input type="hidden" name="evx_action" value="check_balance">
                    <button class="btn btn-default btn-sm" type="submit"><i class="fas fa-wallet"></i> Check wallet</button>
                </form>
                <form method="post" action="{$link}&amp;page=dashboard">
                    <input type="hidden" name="token" value="{$csrf}"><input type="hidden" name="evx_action" value="run_sync">
                    <button class="btn btn-default btn-sm" type="submit"><i class="fas fa-sync-alt"></i> Sync now</button>
                </form>
                <a class="btn btn-default btn-sm" href="{$link}&amp;page=plans"><i class="fas fa-file-import"></i> Import plans</a>
            </div>
        </div>
        {/if}
    </div>
</div>
