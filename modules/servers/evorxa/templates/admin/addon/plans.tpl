{if $importReport}
<div class="evxm-card">
    <h3>Import result</h3>
    <div class="evxm-table">
    <table class="table table-condensed">
        <thead><tr><th>Plan</th><th>Result</th><th>Product</th><th>Your prices</th><th>Notes</th></tr></thead>
        {foreach $importReport.rows as $r}
            <tr>
                <td>{$r.plan|escape}</td>
                <td><span class="evxm-badge {if $r.status == 'created'}evxm-ok{elseif $r.status == 'error'}evxm-bad{/if}">{$r.status|escape}</span></td>
                <td>{if $r.pid}<a href="configproducts.php?action=edit&amp;id={$r.pid}">{$r.name|escape}</a>{/if}</td>
                <td>{if $r.prices}{foreach $r.prices as $cycle => $price}<span class="evxm-muted">{$cycle}</span> {$price|escape}&nbsp; {/foreach}{/if}</td>
                <td class="evxm-muted">{$r.message|escape}</td>
            </tr>
        {/foreach}
    </table>
    </div>
    <p class="evxm-muted" style="margin:10px 0 0">Products are created hidden unless you unticked that option. Review them in <a href="configproducts.php">Setup &gt; Products/Services</a>, then untick "Hide" to publish.</p>
</div>
{/if}

<form method="post" action="{$link}&amp;page=plans">
<input type="hidden" name="token" value="{$csrf}">
<input type="hidden" name="evx_action" value="import">

<div class="evxm-card">
    <h3>Evorxa plans</h3>
    <p class="evxm-muted">Live prices from your Evorxa account (what Evorxa charges your wallet). Tick the plans to sell, set your markup below and import. Plans already sold in a product show your monthly price and margin.</p>
    <div class="evxm-table">
    <table class="table table-condensed">
        <thead><tr><th style="width:30px"><input type="checkbox" onclick="var c=this.checked;document.querySelectorAll('.evxm-plan').forEach(function(x){ldelim}x.checked=c{rdelim})"></th><th>Plan</th><th>Specs</th><th>Stock</th><th class="text-right">Monthly</th><th class="text-right">6 months</th><th class="text-right">Yearly</th><th>Your products</th></tr></thead>
        <tbody>
        {assign var=lastCat value=''}
        {foreach $plans as $p}
            {if $p.category != $lastCat}
                <tr class="evxm-cat"><td colspan="8">{$p.category|escape}</td></tr>
                {assign var=lastCat value=$p.category}
            {/if}
            <tr>
                <td><input type="checkbox" class="evxm-plan" name="packages[]" value="{$p.id}"{if !$p.imported} checked{/if}></td>
                <td><strong>{$p.name|escape}</strong> <span class="evxm-muted">#{$p.id}</span></td>
                <td class="evxm-muted">{$p.specs|escape}</td>
                <td>{if $p.stock > 0}{$p.stock}{else}<span class="evxm-badge evxm-bad">sold out</span>{/if}</td>
                <td class="text-right">${$p.monthly}</td>
                <td class="text-right">${$p.semi}</td>
                <td class="text-right">${$p.yearly}</td>
                <td>
                    {foreach $p.products as $prod}
                        <div><a href="configproducts.php?action=edit&amp;id={$prod.id}">{$prod.name|escape}</a>
                            {if $prod.hidden}<span class="evxm-badge">hidden</span>{/if}
                            {if $prod.monthlyText} <span class="evxm-muted">{$prod.monthlyText}/mo</span>{/if}
                            {if $prod.margin} <span class="evxm-badge evxm-ok">{$prod.margin} margin</span>{/if}
                        </div>
                    {foreachelse}
                        <span class="evxm-muted">-</span>
                    {/foreach}
                </td>
            </tr>
        {/foreach}
        </tbody>
    </table>
    </div>
</div>

<div class="evxm-card">
    <h3>Import selected plans</h3>
    <div class="evxm-form-grid">
        <div class="form-group">
            <label>Product group</label>
            <select name="group_id" class="form-control">
                <option value="0">+ New group (name below)</option>
                {foreach $groups as $g}<option value="{$g.id}"{if $g.id == $defaultGroup} selected{/if}>{$g.name|escape}</option>{/foreach}
            </select>
            <input type="text" name="new_group" class="form-control" style="margin-top:6px" placeholder="New group name, e.g. Cloud VPS">
        </div>
        <div class="form-group">
            <label>Markup on Evorxa's price (%)</label>
            <input type="number" name="markup" value="40" step="1" class="form-control">
            <p class="evxm-muted">40% turns $2.99 into $4.99 with .99 rounding.</p>
        </div>
        <div class="form-group">
            <label>Price rounding</label>
            <select name="rounding" class="form-control">
                <option value="99" selected>Up to .99 (4.19 &rarr; 4.99)</option>
                <option value="whole">Up to a whole number (4.19 &rarr; 5.00)</option>
                <option value="none">Exact (4.19)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Billing terms to sell</label>
            <div class="checkbox" style="margin:2px 0"><label><input type="checkbox" name="cycles[]" value="monthly" checked> Monthly</label></div>
            <div class="checkbox" style="margin:2px 0"><label><input type="checkbox" name="cycles[]" value="semiannually" checked> 6 months (Evorxa gives 10% off)</label></div>
            <div class="checkbox" style="margin:2px 0"><label><input type="checkbox" name="cycles[]" value="annually" checked> Yearly (Evorxa gives 20% off)</label></div>
        </div>
    </div>
    <div class="checkbox"><label><input type="checkbox" name="hidden" value="1" checked> Create products hidden (review before publishing)</label></div>
    <div class="checkbox"><label><input type="checkbox" name="options" value="1" checked> Let clients choose the operating system / one-click app, hostname and SSH key when ordering</label></div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> Import selected plans</button>
    <span class="evxm-muted">&nbsp; Plans already in the chosen group are skipped. Stock is kept in sync every hour.</span>
</div>
</form>

<form method="post" action="{$link}&amp;page=plans">
    <input type="hidden" name="token" value="{$csrf}"><input type="hidden" name="evx_action" value="sync_stock">
    <button type="submit" class="btn btn-default btn-sm"><i class="fas fa-boxes"></i> Sync stock now</button>
</form>
