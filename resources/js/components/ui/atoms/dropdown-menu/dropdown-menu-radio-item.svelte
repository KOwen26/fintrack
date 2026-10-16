<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn.js';

    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        checkClass,
        children: childrenProp,
        ...restProps
    }: WithoutChild<DropdownMenuPrimitive.RadioItemProps> & { checkClass?: string } = $props();
</script>

<DropdownMenuPrimitive.RadioItem
    data-slot="dropdown-menu-radio-item"
    class={cn(
        "relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground data-disabled:pointer-events-none data-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        'data-[state=checked]:bg-accent-200',
        className
    )}
    bind:ref
    {...restProps}>
    {#snippet children({ checked })}
        <span
            class={cn(
                'pointer-events-none absolute left-2 flex size-4 items-center justify-center',
                checkClass
            )}>
            {#if checked}
                <i class="iconify size-5 fill-current solar--check-linear"></i>
            {/if}
        </span>
        {@render childrenProp?.({ checked })}
    {/snippet}
</DropdownMenuPrimitive.RadioItem>
