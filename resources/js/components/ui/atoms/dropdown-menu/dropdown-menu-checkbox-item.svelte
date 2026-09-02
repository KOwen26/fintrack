<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';
    import type { Snippet } from 'svelte';

    import { DropdownMenu as DropdownMenuPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        checked = $bindable(false),
        indeterminate = $bindable(false),
        class: className,
        children: childrenProp,
        ...restProps
    }: WithoutChildrenOrChild<DropdownMenuPrimitive.CheckboxItemProps> & {
        children?: Snippet;
    } = $props();
</script>

<DropdownMenuPrimitive.CheckboxItem
    data-slot="dropdown-menu-checkbox-item"
    class={cn(
        "relative flex cursor-default items-center gap-2 rounded-sm py-1.5 pr-2 pl-8 text-sm outline-hidden select-none focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className
    )}
    bind:ref
    bind:checked
    bind:indeterminate
    {...restProps}>
    {#snippet children({ checked, indeterminate })}
        <span class="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
            {#if indeterminate}
                <i class="iconify size-4 solar--minus-line-duotone"></i>
            {:else}
                <i class={cn('iconify size-4 tabler--check', !checked && 'text-transparent')}></i>
            {/if}
        </span>
        {@render childrenProp?.()}
    {/snippet}
</DropdownMenuPrimitive.CheckboxItem>
