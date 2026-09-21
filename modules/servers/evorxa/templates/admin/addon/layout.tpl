{literal}<style>
.evxm{max-width:1200px}
.evxm .evxm-nav{margin-bottom:18px}
.evxm .evxm-card{background:#fff;border:1px solid #e3e6ea;border-radius:8px;padding:16px 18px;margin-bottom:16px}
.evxm .evxm-card h3{margin:0 0 12px;font-size:16px;font-weight:600}
.evxm .evxm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:16px}
.evxm .evxm-stat{background:#fff;border:1px solid #e3e6ea;border-radius:8px;padding:12px 14px}
.evxm .evxm-stat small{display:block;color:#6b7280;font-size:12px}
.evxm .evxm-stat strong{font-size:20px}
.evxm .evxm-check{display:flex;gap:10px;padding:8px 0;border-top:1px solid #f0f1f3}
.evxm .evxm-check:first-of-type{border-top:0}
.evxm .evxm-check .ic{width:20px;text-align:center;font-size:15px}
.evxm .evxm-check .ok{color:#16a34a}.evxm .evxm-check .no{color:#d97706}
.evxm .evxm-muted{color:#6b7280;font-size:12px}
.evxm .evxm-badge{display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;background:#eef0f3;color:#374151;white-space:nowrap}
.evxm .evxm-ok{background:#dcfce7;color:#166534}.evxm .evxm-bad{background:#fee2e2;color:#991b1b}.evxm .evxm-warn{background:#fef3c7;color:#92400e}.evxm .evxm-busy{background:#dbeafe;color:#1e40af}
.evxm table.table{background:#fff;margin-bottom:0}
.evxm table.table td,.evxm table.table th{vertical-align:middle}
.evxm .evxm-table{border:1px solid #e3e6ea;border-radius:8px;overflow:auto;margin-bottom:16px}
.evxm .evxm-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px 18px}
.evxm .evxm-cat{background:#f8fafc;font-weight:600}
.evxm code{font-size:11.5px}
</style>{/literal}
<div class="evxm">
    <ul class="nav nav-tabs evxm-nav">
        {foreach $pages as $key => $label}
            <li{if $page == $key} class="active"{/if}><a href="{$link}&amp;page={$key}">{$label}</a></li>
        {/foreach}
    </ul>
    {foreach $flash as $f}
        <div class="alert alert-{$f.type}">{$f.message|escape}</div>
    {/foreach}
    {include file="`$tplDir`/admin/addon/`$page`.tpl"}
</div>
