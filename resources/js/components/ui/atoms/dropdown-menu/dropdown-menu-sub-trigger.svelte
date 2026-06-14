<script lang="ts">
    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        inset,
        children,
        ...restProps
    }: DropdownMenuPrimitive.SubTriggerProps & {
        inset?: boolean;
    } = $props();
</script>

<DropdownMenuPrimitive.SubTrigger
    class={cn(
        "data-highlighted:bg-accent data-highlighted:text-accent-foreground data-[state=open]:bg-accent data-[state=open]:text-accent-foreground [&_svg:not([class*='text-'])]:text-muted-foreground flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className
    )}
    data-inset={inset}
    data-slot="dropdown-menu-sub-trigger"
    bind:ref
    {...restProps}>
    {@render children?.()}
    <i class="iconify ph--caret-right-duotone ml-auto size-4"></i>
</DropdownMenuPrimitive.SubTrigger>
