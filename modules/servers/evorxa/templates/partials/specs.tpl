{if $evx.specs}
<div class="evx-specs">
    {foreach $evx.specs as $spec}
        <div class="evx-spec">
            <span class="evx-spec-icon" aria-hidden="true"><i class="fas fa-{$spec.icon}"></i></span>
            <div>
                <div class="evx-spec-label">{$spec.label|escape}</div>
                <div class="evx-spec-value">{$spec.value|escape}</div>
            </div>
        </div>
    {/foreach}
</div>
{/if}
