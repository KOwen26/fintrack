<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';
    import type { Snippet } from 'svelte';

    import { Menubar as MenubarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        checked = $bindable(false),
        indeterminate = $bindable(false),
        children: childrenProp,
        ...restProps
    }: WithoutChildrenOrChild<MenubarPrimitive.CheckboxItemProps> & {
        children?: Snippet;
    } = $props();
</script>

<MenubarPrimitive.CheckboxItem
    data-slot="menubar-checkbox-item"
    class={cn(
        "focus:bg-accent focus:text-accent-foreground relative flex cursor-default items-center gap-2 rounded-xs py-1.5 pr-2 pl-8 text-sm outline-hidden select-none data-[disabled]:pointer-events-none data-[disabled]:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4",
        className
    )}
    bind:ref
    bind:checked
    bind:indeterminate
    {...restProps}>
    {#snippet children({ checked, indeterminate })}
        <span class="pointer-events-none absolute left-2 flex size-3.5 items-center justify-center">
            {#if indeterminate}
                <i class="iconify ph--minus-duotone size-4"></i>
            {:else}
                <i class={cn('iconify ph--check-duotone size-4', !checked && 'text-transparent')}
                ></i>
            {/if}
        </span>
        {@render childrenProp?.()}
    {/snippet}
</MenubarPrimitive.CheckboxItem>
