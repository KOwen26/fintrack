<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn.js';

    import { Select as SelectPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithoutChild<SelectPrimitive.TriggerProps> = $props();
</script>

<SelectPrimitive.Trigger
    data-slot="select-trigger"
    class={cn(
        'flex h-12 w-full min-w-0 items-center justify-between gap-2 rounded-lg border border-input bg-transparent px-3 py-1 text-sm whitespace-nowrap transition-[color,box-shadow] outline-none select-none md:h-10 dark:bg-input/30',
        'focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/50',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-input/50 disabled:opacity-50 dark:disabled:bg-input/80',
        '*:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2',
        "[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*='size-'])]:size-4 [&_svg:not([class*='text-'])]:text-muted-foreground",
        className
    )}
    bind:ref
    {...restProps}>
    {@render children?.()}
    <i class="iconify size-5 shrink-0 text-muted-foreground solar--alt-arrow-down-linear"></i>
</SelectPrimitive.Trigger>
