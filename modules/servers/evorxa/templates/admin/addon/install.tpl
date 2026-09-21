<div class="row">
    <div class="col-lg-5">
        <div class="evxm-card">
            <h3><i class="fas fa-box-open" style="color:#e2445c"></i> Install on another WHMCS</h3>
            <p style="margin:0 0 12px">Download this module as a ready-to-upload package and install it on any WHMCS. The package is built from the files installed here, so it always matches this version.</p>
            <table class="table table-condensed" style="margin-bottom:14px">
                <tr><td class="evxm-muted">Version</td><td><strong>{$version}</strong></td></tr>
                <tr><td class="evxm-muted">Contents</td><td>Evorxa Cloud server module + Evorxa Manager addon + INSTALL.txt</td></tr>
                <tr><td class="evxm-muted">Size</td><td>{$package.files} files, {$package.size}</td></tr>
                <tr><td class="evxm-muted">Not included</td><td>Your settings, API token, products and customer data (they stay on this WHMCS)</td></tr>
            </table>
            {if $package.missing}
                <div class="alert alert-warning">Missing folder(s): {foreach $package.missing as $m}<code>{$m}</code> {/foreach}</div>
            {elseif !$requirements.zip}
                <div class="alert alert-warning">The PHP zip extension is not available on this server, so the package cannot be built here.</div>
            {else}
                <form method="post" action="{$link}&amp;page=install">
                    <input type="hidden" name="token" value="{$csrf}">
                    <input type="hidden" name="evx_action" value="download_package">
                    <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-download"></i> Download {$packageName}</button>
                </form>
            {/if}
        </div>

        <div class="evxm-card">
            <h3><i class="fas fa-clipboard-check"></i> Requirements on this server</h3>
            <table class="table table-condensed" style="margin:0">
                {foreach $requirements.checks as $r}
                    <tr>
                        <td style="width:26px">{if $r.ok}<i class="fas fa-check-circle" style="color:#16a34a"></i>{else}<i class="fas fa-exclamation-circle" style="color:#d97706"></i>{/if}</td>
                        <td>{$r.label|escape}</td>
                        <td class="evxm-muted text-right">{$r.value|escape}</td>
                    </tr>
                {/foreach}
            </table>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="evxm-card">
            <h3><i class="fas fa-list-ol"></i> Installation steps</h3>
            <ol class="evxm-steps">
                <li><strong>Upload</strong> - extract the zip into the WHMCS root folder. It only adds <code>modules/servers/evorxa</code> and <code>modules/addons/evorxa_manager</code>.</li>
                <li><strong>Activate</strong> - <em>Setup &gt; Addon Modules</em>: activate <strong>Evorxa Manager</strong> and tick the admin roles that may use it.</li>
                <li><strong>API token</strong> - in the <a href="{$consoleUrl}" target="_blank" rel="noopener">Evorxa console</a> create a token with:
                    <div class="evxm-copy"><code>instances:read instances:write analytics:read shield:read shield:write wallet:read</code></div></li>
                <li><strong>Add the server</strong> - <em>Setup &gt; Products/Services &gt; Servers &gt; Add New Server</em>: module <strong>Evorxa Cloud</strong>, hostname
                    <div class="evxm-copy"><code>api.evorxa.com</code></div>
                    paste the token into <strong>Password</strong>, then <strong>Test Connection</strong>.</li>
                <li><strong>Settings</strong> - <em>Addons &gt; Evorxa Manager &gt; Settings</em>: choose the Evorxa project for client servers and your hostname suffix.</li>
                <li><strong>Products</strong> - <em>Plans &amp; Import</em>: tick plans, set your markup and import. Review the hidden products, then unhide them.</li>
                <li><strong>Cron</strong> - run the WHMCS cron every 5 minutes:
                    <div class="evxm-copy"><code>*/5 * * * * php -q /path/to/whmcs/crons/cron.php</code></div></li>
            </ol>
            <p class="evxm-muted" style="margin:12px 0 0"><i class="fas fa-sync-alt"></i> To update an existing install, extract a newer package over the old files. Settings, servers, logs and <code>lang/overrides</code> are kept.</p>
        </div>
    </div>
</div>
{literal}<style>
.evxm .evxm-steps{margin:0;padding-left:20px}
.evxm .evxm-steps li{padding:8px 0 8px 4px;border-top:1px solid #f1f2f4;line-height:1.55}
.evxm .evxm-steps li:first-child{border-top:0}
.evxm .evxm-copy{margin:6px 0;padding:8px 10px;background:#0f172a;border-radius:8px;overflow-x:auto}
.evxm .evxm-copy code{background:none;color:#e2e8f0;padding:0;white-space:nowrap;font-size:12px}
</style>{/literal}
