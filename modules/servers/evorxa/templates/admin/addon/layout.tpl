{literal}<style>
.evxm{--evxm-accent:#e2445c;--evxm-ink:#111827;--evxm-muted:#6b7280;--evxm-line:#e5e7eb;--evxm-soft:#f8fafc;width:100%;max-width:none;color:var(--evxm-ink)}
.evxm *{box-sizing:border-box}
.evxm .evxm-head{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:18px 22px;margin-bottom:16px;border-radius:12px;background:#fff;border:1px solid var(--evxm-line);box-shadow:0 1px 2px rgba(17,24,39,.04);position:relative;overflow:hidden}
.evxm .evxm-head::after{content:"";position:absolute;inset:auto 0 0 0;height:3px;background:linear-gradient(90deg,var(--evxm-accent),#111827)}
.evxm .evxm-brand{display:flex;align-items:center;gap:12px}
.evxm .evxm-brand img{height:34px;width:auto;display:block}
.evxm .evxm-brand-sub{font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--evxm-muted);border-left:1px solid var(--evxm-line);padding-left:12px}
.evxm .evxm-head-links{display:flex;align-items:center;gap:14px;font-size:13px;color:var(--evxm-muted)}
.evxm .evxm-head-links a{color:var(--evxm-ink);font-weight:500}
.evxm .evxm-head-links a:hover{color:var(--evxm-accent);text-decoration:none}
.evxm .evxm-nav{display:flex;gap:4px;flex-wrap:wrap;margin:0 0 18px;padding:4px;background:#fff;border:1px solid var(--evxm-line);border-radius:10px;list-style:none}
.evxm .evxm-nav a{display:inline-flex;align-items:center;gap:8px;padding:8px 14px;border-radius:7px;color:var(--evxm-muted);font-weight:600;font-size:13px}
.evxm .evxm-nav a:hover{background:var(--evxm-soft);color:var(--evxm-ink);text-decoration:none}
.evxm .evxm-nav a.active{background:var(--evxm-ink);color:#fff}
.evxm .evxm-nav a i{font-size:12px;opacity:.85}
.evxm .evxm-card{background:#fff;border:1px solid var(--evxm-line);border-radius:12px;padding:18px 20px;margin-bottom:16px;box-shadow:0 1px 2px rgba(17,24,39,.04)}
.evxm .evxm-card h3{margin:0 0 14px;font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px}
.evxm .evxm-card h3 .evxm-right{margin-left:auto;font-size:12px;font-weight:500}
.evxm .evxm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:16px}
.evxm .evxm-kpi{background:#fff;border:1px solid var(--evxm-line);border-radius:12px;padding:16px 18px;display:flex;gap:14px;align-items:flex-start;box-shadow:0 1px 2px rgba(17,24,39,.04)}
.evxm .evxm-kpi-ic{flex:0 0 40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;background:#fdecef;color:var(--evxm-accent)}
.evxm .evxm-kpi-ic.ink{background:#eef0f4;color:var(--evxm-ink)}.evxm .evxm-kpi-ic.ok{background:#dcfce7;color:#15803d}.evxm .evxm-kpi-ic.warn{background:#fef3c7;color:#b45309}
.evxm .evxm-kpi small{display:block;color:var(--evxm-muted);font-size:12px;font-weight:600;margin-bottom:2px}
.evxm .evxm-kpi strong{display:block;font-size:22px;line-height:1.2;font-weight:700}
.evxm .evxm-kpi .evxm-sub{font-size:12px;color:var(--evxm-muted);margin-top:4px}
.evxm .evxm-muted{color:var(--evxm-muted);font-size:12px}
.evxm .evxm-badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;background:#eef0f3;color:#374151;white-space:nowrap}
.evxm .evxm-ok{background:#dcfce7;color:#166534}.evxm .evxm-bad{background:#fee2e2;color:#991b1b}.evxm .evxm-warn{background:#fef3c7;color:#92400e}.evxm .evxm-busy{background:#dbeafe;color:#1e40af}
.evxm .evxm-progress{height:8px;border-radius:999px;background:#f1f2f4;overflow:hidden;margin:2px 0 12px}
.evxm .evxm-progress span{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--evxm-accent),#f0768a)}
.evxm .evxm-check{display:flex;align-items:flex-start;gap:12px;padding:11px 0;border-top:1px solid #f1f2f4}
.evxm .evxm-check:first-of-type{border-top:0}
.evxm .evxm-check .ic{flex:0 0 22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;margin-top:1px}
.evxm .evxm-check .ic.ok{background:#16a34a;color:#fff}.evxm .evxm-check .ic.no{background:#fef3c7;color:#b45309}
.evxm .evxm-check .txt{flex:1 1 auto;min-width:0}
.evxm .evxm-check .txt div:first-child{font-weight:600}
.evxm .evxm-check .btn{flex:0 0 auto}
.evxm .evxm-money{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.evxm .evxm-money div{background:var(--evxm-soft);border-radius:10px;padding:12px 14px}
.evxm .evxm-money small{display:block;color:var(--evxm-muted);font-size:12px;font-weight:600}
.evxm .evxm-money strong{font-size:18px}
.evxm .evxm-money .pos{color:#15803d}.evxm .evxm-money .neg{color:#b91c1c}
.evxm .evxm-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.evxm table.table{background:#fff;margin-bottom:0}
.evxm table.table td,.evxm table.table th{vertical-align:middle}
.evxm table.table thead th{font-size:11.5px;text-transform:uppercase;letter-spacing:.03em;color:var(--evxm-muted);border-bottom-width:1px}
.evxm .evxm-table{border:1px solid var(--evxm-line);border-radius:10px;overflow:auto;margin-bottom:16px;background:#fff}
.evxm .evxm-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px 18px}
.evxm .evxm-cat{background:var(--evxm-soft);font-weight:700}
.evxm .evxm-empty{text-align:center;padding:22px 10px;color:var(--evxm-muted)}
.evxm .evxm-empty i{display:block;font-size:22px;margin-bottom:6px;color:#16a34a}
.evxm .btn-primary{background:var(--evxm-ink);border-color:var(--evxm-ink)}
.evxm .btn-primary:hover,.evxm .btn-primary:focus{background:var(--evxm-accent);border-color:var(--evxm-accent)}
.evxm code{font-size:11.5px}
@media (max-width:767px){.evxm .evxm-money{grid-template-columns:1fr}}
</style>{/literal}
<div class="evxm">
    <div class="evxm-head">
        <div class="evxm-brand">
            <img src="{$brandLogo}" alt="Evorxa">
            <span class="evxm-brand-sub">Manager</span>
        </div>
        <div class="evxm-head-links">
            <a href="{$consoleUrl}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Evorxa console</a>
            <a href="https://evorxa.com/docs" target="_blank" rel="noopener"><i class="fas fa-book"></i> API docs</a>
            <span>v{$version}</span>
        </div>
    </div>
    <ul class="evxm-nav">
        {foreach $pages as $key => $label}
            <li><a href="{$link}&amp;page={$key}"{if $page == $key} class="active"{/if}><i class="fas {$icons.$key}"></i>{$label}</a></li>
        {/foreach}
    </ul>
    {foreach $flash as $f}
        <div class="alert alert-{$f.type}">{$f.message|escape}</div>
    {/foreach}
    {include file="`$tplDir`/admin/addon/`$page`.tpl"}
</div>
