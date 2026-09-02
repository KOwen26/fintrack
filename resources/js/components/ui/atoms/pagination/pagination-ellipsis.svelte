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
    data-slot="pagination-ellipsis"
    class={cn('flex size-9 items-center justify-center', className)}
    aria-hidden="true"
    {...restProps}>
    <i class="iconify size-4 solar--menu-dots-line-duotone"></i>
    <span class="sr-only">More pages</span>
</span>
