<script lang="ts">
    import type { WithElementRef, WithoutChildren } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: WithoutChildren<WithElementRef<HTMLAttributes<HTMLSpanElement>>> = $props();
</script>

<span
    bind:this={ref}
    class={cn('flex size-9 items-center justify-center', className)}
    aria-hidden="true"
    data-slot="pagination-ellipsis"
    {...restProps}>
    <i class="iconify ph--dots-three-duotone size-4"></i>
    <span class="sr-only">More pages</span>
</span>
