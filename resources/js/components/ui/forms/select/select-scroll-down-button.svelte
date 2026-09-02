<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';

    import { Select as SelectPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: WithoutChildrenOrChild<SelectPrimitive.ScrollDownButtonProps> = $props();
</script>

<SelectPrimitive.ScrollDownButton
    data-slot="select-scroll-down-button"
    class={cn('flex cursor-default items-center justify-center py-1', className)}
    bind:ref
    {...restProps}>
    <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
</SelectPrimitive.ScrollDownButton>
