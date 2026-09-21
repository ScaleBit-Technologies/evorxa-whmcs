<section class="evx-card evx-pending" data-evx="pending">
    <div class="evx-spinner" aria-hidden="true"></div>
    <h4>{$evx.t.pending_title|escape}</h4>
    <p class="evx-muted">{$evx.t.pending_text|escape}</p>
    <ol class="evx-steps" data-evx="steps">
        <li class="is-done" data-step="order"><span class="evx-step-dot" aria-hidden="true"></span><span>{$evx.t.step_order|escape}</span></li>
        <li class="{if $evx.phase == 'install'}is-done{else}is-active{/if}" data-step="build"><span class="evx-step-dot" aria-hidden="true"></span><span>{$evx.t.step_build|escape}</span></li>
        <li class="{if $evx.phase == 'install'}is-active{/if}" data-step="install"><span class="evx-step-dot" aria-hidden="true"></span><span>{if $evx.app}{$evx.t.step_app|escape}{else}{$evx.t.step_os|escape}{/if}</span></li>
        <li data-step="ready"><span class="evx-step-dot" aria-hidden="true"></span><span>{$evx.t.step_ready|escape}</span></li>
    </ol>
</section>
