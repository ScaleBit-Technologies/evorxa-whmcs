<div class="evxm-grid">
    <div class="evxm-stat"><small>Evorxa wallet</small><strong>{if $account}{$account.balance|escape}{else}-{/if}</strong>{if $account && $account.low} <span class="evxm-badge evxm-warn">low</span>{/if}</div>
    <div class="evxm-stat"><small>Next Evorxa renewal</small><strong style="font-size:14px">{if $account}{$account.renewal|escape}{else}-{/if}</strong></div>
    <div class="evxm-stat"><small>Active servers</small><strong>{if $counts.active}{$counts.active}{else}0{/if}</strong>{if $counts.provisioning} <span class="evxm-badge evxm-busy">{$counts.provisioning} building</span>{/if}</div>
    <div class="evxm-stat"><small>Need attention</small><strong>{$attentionCount}</strong></div>
</div>

<div class="row">
    <div class="col-md-6">
        <div class="evxm-card">
            <h3>Setup checklist</h3>
            {foreach $checks as $c}
                <div class="evxm-check">
                    <span class="ic">{if $c.ok}<i class="fas fa-check-circle ok"></i>{else}<i class="fas fa-exclamation-circle no"></i>{/if}</span>
                    <div>
                        <div><a href="{$c.url}">{$c.label|escape}</a></div>
                        {if !$c.ok}<div class="evxm-muted">{$c.hint|escape}</div>{/if}
                    </div>
                </div>
            {/foreach}
        </div>
        {if $account}
        <div class="evxm-card">
            <h3>Evorxa account</h3>
            <p class="evxm-muted" style="margin:0 0 6px">Connected as <strong>{$account.email|escape}</strong>{if $server} via server <a href="configservers.php?action=manage&amp;id={$server.id}">{$server.name|escape}</a>{/if}.</p>
            <p class="evxm-muted" style="margin:0 0 10px">Account auto-renew: <strong>{if $account.autoRenew}on{else}off{/if}</strong>. {if $account.autoRenew}Evorxa renews each server from your wallet 5 days before it expires; the module only reactivates or stops servers to match what clients paid.{else}The module extends each server only after the client has paid.{/if}</p>
            <form method="post" action="{$link}&amp;page=dashboard" style="display:inline">
                <input type="hidden" name="token" value="{$csrf}"><input type="hidden" name="evx_action" value="check_balance">
                <button class="btn btn-default btn-sm" type="submit"><i class="fas fa-wallet"></i> Check wallet now</button>
            </form>
            <form method="post" action="{$link}&amp;page=dashboard" style="display:inline">
                <input type="hidden" name="token" value="{$csrf}"><input type="hidden" name="evx_action" value="run_sync">
                <button class="btn btn-default btn-sm" type="submit"><i class="fas fa-sync-alt"></i> Run sync now</button>
            </form>
        </div>
        {/if}
    </div>
    <div class="col-md-6">
        <div class="evxm-card">
            <h3>Needs attention</h3>
            {if $attention}
                <table class="table table-condensed">
                    {foreach $attention as $a}
                        <tr>
                            <td><a href="clientsservices.php?id={$a.service}">Service #{$a.service}</a></td>
                            <td><span class="evxm-badge evxm-bad">{$a.state|escape}</span></td>
                            <td class="evxm-muted">{$a.error|escape}</td>
                        </tr>
                    {/foreach}
                </table>
            {else}
                <p class="evxm-muted" style="margin:0">Nothing to do. Failed or unconfirmed orders show up here.</p>
            {/if}
        </div>
        <div class="evxm-card">
            <h3>Recent activity <a class="evxm-muted" style="font-weight:400" href="{$link}&amp;page=log">(all)</a></h3>
            {include file="`$tplDir`/admin/addon/logtable.tpl" logs=$recent}
        </div>
    </div>
</div>
