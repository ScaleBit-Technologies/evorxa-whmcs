{* Uses the native product-details variables and WHMCS language strings, so it matches the theme. *}
<section class="evx-card evx-billing">
    <header class="evx-card-head">
        <h4><i class="far fa-credit-card" aria-hidden="true"></i>{$evx.t.billing_title|escape}</h4>
        <div class="evx-billing-actions">
            {if $packagesupgrade}<a href="upgrade.php?type=package&amp;id={$id}" class="btn btn-default btn-sm">{$LANG.upgradedowngradepackage}</a>{/if}
            {if $showcancelbutton}<a href="clientarea.php?action=cancel&amp;id={$id}" class="btn btn-default btn-sm">{$LANG.clientareacancelrequestbutton}</a>{/if}
        </div>
    </header>
    <dl class="evx-dl">
        <div><dt>{$LANG.orderproduct}</dt><dd>{if $groupname}{$groupname} - {/if}{$product}</dd></div>
        <div><dt>{$LANG.clientareahostingregdate}</dt><dd>{$regdate}</dd></div>
        <div><dt>{$LANG.recurringamount}</dt><dd>{$recurringamount}{if $billingcycle} <span class="evx-muted">({$billingcycle})</span>{/if}</dd></div>
        <div><dt>{$LANG.clientareahostingnextduedate}</dt><dd>{$nextduedate}</dd></div>
        <div><dt>{$LANG.orderpaymentmethod}</dt><dd>{$paymentmethod}</dd></div>
    </dl>
    {if $evx.cancelPending}<p class="evx-note">{$evx.t.cancel_pending|escape}</p>{/if}
</section>
