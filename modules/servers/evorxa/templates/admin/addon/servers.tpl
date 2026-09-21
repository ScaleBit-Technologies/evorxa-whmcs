<p class="evxm-muted">Servers in {foreach $projectNames as $n name=pn}{$n|escape}{if !$smarty.foreach.pn.last}, {/if}{foreachelse}no project yet (choose one in Settings){/foreach}. Only projects used by this WHMCS are listed; your other Evorxa projects are never shown or touched.</p>

{if $missing}
<div class="alert alert-danger">
    <strong>Missing upstream:</strong>
    {foreach $missing as $m}<a href="clientsservices.php?id={$m.service}">service #{$m.service}</a> (<code>{$m.instance|escape}</code>) {/foreach}
    - these services are linked to servers that no longer exist in their Evorxa project.
</div>
{/if}

<div class="evxm-table">
<table class="table table-condensed">
    <thead><tr><th>Server</th><th>IP</th><th>Plan</th><th>Status</th><th>Paid until</th><th>WHMCS service</th><th></th></tr></thead>
    <tbody>
    {foreach $servers as $s}
        <tr{if $s.flag} class="warning"{/if}>
            <td><strong>{$s.hostname|escape}</strong><div class="evxm-muted"><code>{$s.id|escape}</code> {$s.name|escape}</div></td>
            <td><code>{$s.ip|escape}</code></td>
            <td>{$s.plan|escape}</td>
            <td><span class="evxm-badge {if $s.status == 'running'}evxm-ok{elseif $s.status == 'suspended'}evxm-bad{/if}">{$s.status|escape}</span></td>
            <td>{$s.billedTill|escape}{if $s.deletion}<div><span class="evxm-badge evxm-warn">deletes {$s.deletion|escape}</span></div>{/if}</td>
            <td>
                {if $s.service}
                    <a href="clientsservices.php?userid={$s.client}&amp;id={$s.service}">#{$s.service}</a> <span class="evxm-muted">{$s.serviceStatus|escape}</span>
                {else}
                    <span class="evxm-badge">unmanaged</span>
                {/if}
            </td>
            <td class="evxm-muted">{$s.flag|escape}</td>
        </tr>
    {foreachelse}
        <tr><td colspan="7" class="evxm-muted">No servers yet.</td></tr>
    {/foreach}
    </tbody>
</table>
</div>
<p class="evxm-muted">To attach an existing server to a service, open the service in WHMCS and paste the server id into the Evorxa tab's "Link existing server" field.</p>
