<script lang="ts">
    import type { ToggleVariants } from './toggle.svelte';

    import { getToggleGroupCtx } from './toggle-group.svelte';
    import { toggleVariants } from './toggle.svelte';

    import { ToggleGroup as ToggleGroupPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        value = $bindable(),
        class: className,
        size,
        variant,
        ...restProps
    }: ToggleGroupPrimitive.ItemProps & ToggleVariants = $props();

    const ctx = getToggleGroupCtx();
</script>

<ToggleGroupPrimitive.Item
    data-slot="toggle-group-item"
    class={cn(
        toggleVariants({
            variant: ctx.variant || variant,
            size: ctx.size || size,
        }),
        'min-w-0 flex-1 shrink-0 rounded-none shadow-none first:rounded-l-md last:rounded-r-md focus:z-10 focus-visible:z-10 data-[variant=outline]:border-l-0 data-[variant=outline]:first:border-l',
        className
    )}
    data-size={ctx.size || size}
    data-variant={ctx.variant || variant}
    {value}
    bind:ref
    {...restProps} />
