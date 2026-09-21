{if $logs}
<div class="evxm-table">
<table class="table table-condensed">
    <thead><tr><th>When</th><th>Service</th><th>By</th><th>Action</th><th>Details</th><th class="text-right">Wallet</th></tr></thead>
    <tbody>
    {foreach $logs as $l}
        <tr>
            <td style="white-space:nowrap">{$l.when|escape}</td>
            <td>{if $l.service}<a href="clientsservices.php?id={$l.service}">#{$l.service}</a>{else}-{/if}</td>
            <td class="evxm-muted">{$l.actor|escape}</td>
            <td><span class="evxm-badge {if $l.ok}evxm-ok{else}evxm-bad{/if}">{$l.action|escape}</span></td>
            <td>{$l.message|escape}</td>
            <td class="text-right" style="white-space:nowrap">{$l.amount|escape}</td>
        </tr>
    {/foreach}
    </tbody>
</table>
</div>
{else}
<p class="evxm-muted" style="margin:0">No activity yet.</p>
{/if}
