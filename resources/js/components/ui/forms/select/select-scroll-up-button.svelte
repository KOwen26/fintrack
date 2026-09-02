<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';

    import { Select as SelectPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: WithoutChildrenOrChild<SelectPrimitive.ScrollUpButtonProps> = $props();
</script>

<SelectPrimitive.ScrollUpButton
    data-slot="select-scroll-up-button"
    class={cn('flex cursor-default items-center justify-center py-1', className)}
    bind:ref
    {...restProps}>
    <i class="iconify size-4 solar--alt-arrow-up-line-duotone"></i>
</SelectPrimitive.ScrollUpButton>
