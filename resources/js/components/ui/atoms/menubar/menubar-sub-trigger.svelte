<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn.js';

    import { Menubar as MenubarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        inset = undefined,
        children,
        ...restProps
    }: WithoutChild<MenubarPrimitive.SubTriggerProps> & {
        inset?: boolean;
    } = $props();
</script>

<MenubarPrimitive.SubTrigger
    data-slot="menubar-sub-trigger"
    class={cn(
        'flex cursor-default items-center rounded-sm px-2 py-1.5 text-sm outline-none select-none focus:bg-accent focus:text-accent-foreground data-[inset]:pl-8 data-[state=open]:bg-accent data-[state=open]:text-accent-foreground',
        className
    )}
    data-inset={inset}
    bind:ref
    {...restProps}>
    {@render children?.()}
    <i class="ml-auto iconify size-4 solar--alt-arrow-right-line-duotone"></i>
</MenubarPrimitive.SubTrigger>
