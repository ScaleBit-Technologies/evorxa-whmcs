<form method="get" action="addonmodules.php" class="form-inline" style="margin-bottom:12px">
    <input type="hidden" name="module" value="evorxa_manager"><input type="hidden" name="page" value="log">
    <label class="evxm-muted">Service ID</label>
    <input type="text" name="service" value="{$filterService|escape}" class="form-control input-sm" style="width:110px">
    <button class="btn btn-default btn-sm" type="submit">Filter</button>
    {if $filterService}<a class="btn btn-link btn-sm" href="{$link}&amp;page=log">Clear</a>{/if}
</form>
<p class="evxm-muted">Every action sent to Evorxa, by clients, admins and the cron. The Wallet column shows charges (positive) and refunds (negative). Raw API requests are in Utilities &gt; Logs &gt; Module Log when module debug logging is on.</p>
{include file="`$tplDir`/admin/addon/logtable.tpl" logs=$logs}
<div style="margin-top:10px">
    {if $pageNo > 1}<a class="btn btn-default btn-sm" href="{$link}&amp;page=log&amp;service={$filterService}&amp;p={$pageNo-1}">&laquo; Newer</a>{/if}
    {if $hasNext}<a class="btn btn-default btn-sm" href="{$link}&amp;page=log&amp;service={$filterService}&amp;p={$pageNo+1}">Older &raquo;</a>{/if}
</div>
