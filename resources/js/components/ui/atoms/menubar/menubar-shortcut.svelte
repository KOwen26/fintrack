<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLSpanElement>> = $props();
</script>

<span
    bind:this={ref}
    class={cn('text-muted-foreground ml-auto text-xs tracking-widest', className)}
    data-slot="menubar-shortcut"
    {...restProps}>
    {@render children?.()}
</span>
