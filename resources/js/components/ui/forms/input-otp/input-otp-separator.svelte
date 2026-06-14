<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    let {
        ref = $bindable(null),
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();
</script>

<div bind:this={ref} data-slot="input-otp-separator" role="separator" {...restProps}>
    {#if children}
        {@render children?.()}
    {:else}
        <i class="iconify ph--dot-duotone"></i>
    {/if}
</div>
