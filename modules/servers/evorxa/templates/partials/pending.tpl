<section class="evx-card evx-pending" data-evx="pending">
    <div class="evx-spinner" aria-hidden="true"></div>
    <h4>{$evx.t.pending_title|escape}</h4>
    <p class="evx-muted">{$evx.t.pending_text|escape}</p>
    <ol class="evx-steps">
        <li class="is-done"><span></span>{$evx.t.step_order|escape}</li>
        <li class="is-active"><span></span>{$evx.t.step_build|escape}</li>
        <li><span></span>{if $evx.app}{$evx.t.step_app|escape}{else}{$evx.t.step_os|escape}{/if}</li>
        <li><span></span>{$evx.t.step_ready|escape}</li>
    </ol>
</section>
