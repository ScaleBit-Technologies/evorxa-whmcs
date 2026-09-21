{literal}<style>
.evx-admin{font-size:13px;max-width:980px}
.evx-admin table{width:100%;margin:0 0 10px}
.evx-admin td{padding:3px 8px 3px 0;vertical-align:top}
.evx-admin td.k{color:#777;width:160px;white-space:nowrap}
.evx-admin code{font-size:12px}
.evx-admin .evx-badge{display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:600;background:#eee;color:#444}
.evx-admin .evx-ok{background:#dff5e3;color:#1d6b32}.evx-admin .evx-bad{background:#fbe1e1;color:#9b1c1c}.evx-admin .evx-busy{background:#e3edfb;color:#1e4f9b}
.evx-admin .evx-log td{border-top:1px solid #eee;font-size:12px}
</style>{/literal}
<div class="evx-admin">
{if $liveError}<div class="alert alert-warning" style="margin-bottom:8px">{$liveError|escape}</div>{/if}
{if $row && $row.error && $row.state != 'active'}<div class="alert alert-danger" style="margin-bottom:8px"><strong>Last error:</strong> {$row.error|escape}</div>{/if}
{if $cancelPending}<div class="alert alert-info" style="margin-bottom:8px">End-of-period cancellation requested: the upstream renewal is stopped automatically before the next Evorxa charge.</div>{/if}
<table>
  <tr><td class="k">Module state</td><td>
    {if $row}
      <span class="evx-badge {if $row.state == 'active'}evx-ok{elseif $row.state == 'failed' || $row.state == 'unknown'}evx-bad{elseif $row.state == 'provisioning' || $row.state == 'creating'}evx-busy{/if}">{$row.state|escape}</span>
    {else}<span class="evx-badge">not created</span>{/if}
  </td></tr>
  {if $row}
  <tr><td class="k">Evorxa server</td><td>{if $row.instance}<code>{$row.instance|escape}</code>{else}-{/if}{if $row.name} &nbsp;name <code>{$row.name|escape}</code>{/if}{if $row.project} &nbsp;project #{$row.project|escape}{/if}</td></tr>
  <tr><td class="k">Plan</td><td>{$plan|escape}{if $row.cycle} &nbsp;(upstream billed {$row.cycle|escape}){/if}</td></tr>
  <tr><td class="k">Image</td><td>{if $row.app}{$row.os|escape} <span class="evx-badge">app: {$row.app|escape}</span>{else}{$row.os|escape}{/if}</td></tr>
  {/if}
  {if $inst}
  <tr><td class="k">Upstream status</td><td>
    <span class="evx-badge {if $inst.status == 'running'}evx-ok{elseif $inst.suspended}evx-bad{/if}">{$inst.status|escape}</span>
    {if $inst.suspended} <span class="evx-badge evx-bad">suspended upstream</span>{/if}
    {if $inst.billingStatus} &nbsp;billing: {$inst.billingStatus|escape}{/if}
  </td></tr>
  <tr><td class="k">Hostname / IP</td><td>{$inst.hostname|escape} &nbsp;<code>{$inst.ip|escape}</code>{if $inst.location} &nbsp;{$inst.location|escape}{/if}</td></tr>
  <tr><td class="k">Paid upstream until</td><td>{$inst.billedTill|escape}{if $inst.deletion} &nbsp;<span class="evx-badge evx-bad">deletion scheduled {$inst.deletion|escape}</span>{/if}</td></tr>
  {/if}
  {if $row}
  <tr><td class="k">Last sync</td><td>{if $row.lastSync}{$row.lastSync|escape}{else}never{/if}{if $row.notified} &nbsp;ready email sent {$row.notified|escape}{/if}</td></tr>
  {/if}
  <tr><td class="k">Link existing server</td><td>
    <input type="text" name="{$field}" value="" class="form-control input-sm" style="max-width:360px;display:inline-block" placeholder="Evorxa server id, or &quot;unlink&quot;" autocomplete="off">
    <div class="text-muted" style="margin-top:3px">Attach a server that already exists in one of your Evorxa projects, then click Save Changes. Nothing is created or charged.</div>
  </td></tr>
</table>
{if $logs}
<table class="evx-log">
  <tr><td class="k" colspan="5"><strong>Recent activity</strong> &nbsp;<a href="{$managerUrl}">full log</a></td></tr>
  {foreach $logs as $log}
  <tr>
    <td style="white-space:nowrap;width:140px">{$log.when|escape}</td>
    <td style="width:90px">{$log.actor|escape}</td>
    <td style="width:130px"><span class="evx-badge {if $log.ok}evx-ok{else}evx-bad{/if}">{$log.action|escape}</span></td>
    <td>{$log.message|escape}</td>
    <td style="white-space:nowrap;text-align:right">{$log.amount|escape}</td>
  </tr>
  {/foreach}
</table>
{/if}
</div>
