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
        'focus:bg-accent focus:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground flex cursor-default items-center rounded-sm px-2 py-1.5 text-sm outline-none select-none data-[inset]:pl-8',
        className
    )}
    data-inset={inset}
    bind:ref
    {...restProps}>
    {@render children?.()}
    <i class="iconify ph--caret-right-duotone ml-auto size-4"></i>
</MenubarPrimitive.SubTrigger>
