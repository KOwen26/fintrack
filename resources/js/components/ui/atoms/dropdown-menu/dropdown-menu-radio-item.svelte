<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn.js';

    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children: childrenProp,
        ...restProps
    }: WithoutChild<DropdownMenuPrimitive.RadioItemProps> = $props();
</script>

<DropdownMenuPrimitive.RadioItem
    data-slot="dropdown-menu-radio-item"
    class={cn(
        "relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className
    )}
    bind:ref
    {...restProps}>
    {#snippet children({ checked })}
        <span class="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
            {#if checked}
                <i class="iconify size-2 fill-current solar--record-circle-line-duotone"></i>
            {/if}
        </span>
        {@render childrenProp?.({ checked })}
    {/snippet}
</DropdownMenuPrimitive.RadioItem>
