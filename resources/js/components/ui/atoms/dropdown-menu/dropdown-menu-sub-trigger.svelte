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
    data-slot="dropdown-menu-sub-trigger"
    class={cn(
        "flex cursor-default items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-hidden select-none data-highlighted:bg-accent data-highlighted:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 data-[inset]:pl-8 data-[state=open]:bg-accent data-[state=open]:text-accent-foreground [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
        className
    )}
    data-inset={inset}
    bind:ref
    {...restProps}>
    {@render children?.()}
    <i class="ml-auto iconify size-4 solar--alt-arrow-right-line-duotone"></i>
</DropdownMenuPrimitive.SubTrigger>
