<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLParagraphElement>> = $props();
</script>

<p
    bind:this={ref}
    data-slot="card-description"
    class={cn('text-muted-foreground text-sm', className)}
    {...restProps}>
    {@render children?.()}
</p>
