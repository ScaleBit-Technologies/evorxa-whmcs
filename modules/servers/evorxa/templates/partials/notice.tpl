<section class="evx-card evx-notice evx-notice-{$evx.state}">
    {if $evx.state == 'suspended'}
        <i class="fas fa-lock" aria-hidden="true"></i>
        <h4>{$evx.t.suspended_title|escape}</h4>
        <p class="evx-muted">{$evx.t.suspended_text|escape}</p>
        <a href="clientarea.php?action=invoices" class="btn btn-primary">{$evx.t.view_invoices|escape}</a>
    {elseif $evx.state == 'pending'}
        <i class="fas fa-hourglass-half" aria-hidden="true"></i>
        <h4>{$evx.t.pendingpay_title|escape}</h4>
        <p class="evx-muted">{$evx.t.pendingpay_text|escape}</p>
    {elseif $evx.state == 'failed'}
        <i class="fas fa-tools" aria-hidden="true"></i>
        <h4>{$evx.t.failed_title|escape}</h4>
        <p class="evx-muted">{$evx.t.failed_text|escape}</p>
        <a href="submitticket.php" class="btn btn-default">{$evx.t.contact_support|escape}</a>
    {elseif $evx.state == 'closed'}
        <i class="fas fa-archive" aria-hidden="true"></i>
        <h4>{$evx.t.closed_title|escape}</h4>
        <p class="evx-muted">{$evx.t.closed_text|escape}</p>
    {else}
        <i class="fas fa-server" aria-hidden="true"></i>
        <h4>{$evx.t.none_title|escape}</h4>
        <p class="evx-muted">{$evx.t.none_text|escape}</p>
    {/if}
</section>
