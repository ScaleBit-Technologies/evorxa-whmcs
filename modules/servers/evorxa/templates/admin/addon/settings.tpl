<form method="post" action="{$link}&amp;page=settings">
<input type="hidden" name="token" value="{$csrf}">
<input type="hidden" name="evx_action" value="save_settings">

<div class="evxm-card">
    <h3>New servers</h3>
    {if $projectsError}<div class="alert alert-warning">{$projectsError|escape}</div>{/if}
    <div class="evxm-form-grid">
        <div class="form-group">
            <label>Evorxa project for client servers</label>
            <select name="project_id" class="form-control">
                <option value="">- choose -</option>
                {foreach $projects as $id => $name}
                    <option value="{$id}"{if $values.project_id == $id} selected{/if}>{$name|escape} (#{$id})</option>
                {/foreach}
            </select>
            <p class="evxm-muted">Only projects you own are listed. Use a dedicated project (create it in the Evorxa dashboard) so client servers never mix with your own. Existing servers keep the project they were created in.</p>
        </div>
        <div class="form-group">
            <label>Hostname suffix</label>
            <input type="text" name="hostname_suffix" value="{$values.hostname_suffix|escape}" class="form-control" placeholder="example.com">
            <p class="evxm-muted">Servers are named <code>vps&lt;service id&gt;.{if $values.hostname_suffix}{$values.hostname_suffix|escape}{else}example.com{/if}</code>, or <code>&lt;client choice&gt;.{if $values.hostname_suffix}{$values.hostname_suffix|escape}{else}example.com{/if}</code>. This also becomes the reverse DNS, so your brand shows instead of Evorxa's.</p>
        </div>
    </div>
</div>

<div class="evxm-card">
    <h3>Client control panel</h3>
    <p class="evxm-muted">Choose what clients can do from their service page. Everything else (status, IPs, login details, billing) is always shown.</p>
    {foreach $features as $f}
        <div class="checkbox" style="margin:6px 0">
            <label><input type="checkbox" name="feature_{$f.key}" value="1"{if $f.on} checked{/if}> {$f.label|escape}</label>
        </div>
    {/foreach}
</div>

<div class="evxm-card">
    <h3>Alerts</h3>
    <div class="evxm-form-grid">
        <div class="form-group">
            <label>Low wallet alert below (USD)</label>
            <input type="number" min="0" step="1" name="low_balance" value="{$values.low_balance|escape}" class="form-control">
            <p class="evxm-muted">Checked daily. You are also alerted when the balance is below the renewals due in the next 7 days, or an order cannot be provisioned.</p>
        </div>
        <div class="form-group">
            <label>Alert when a build takes longer than (minutes)</label>
            <input type="number" min="10" max="240" name="provision_timeout" value="{$values.provision_timeout|escape}" class="form-control">
            <p class="evxm-muted">Alerts go to admins who receive System emails (Setup &gt; Staff Management &gt; Administrator Users).</p>
        </div>
    </div>
</div>

<div class="evxm-card">
    <h3>Client email</h3>
    <p class="evxm-muted" style="margin:0">When a server is ready, clients receive the <strong>{$emailTemplate|escape}</strong> email (English + Arabic) with the IP, username and password{if $emailTemplateId} - <a href="configemailtemplates.php?action=edit&amp;id={$emailTemplateId}">edit the template</a>{/if}.</p>
</div>

<button type="submit" class="btn btn-primary">Save settings</button>
</form>
