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
    class={cn('text-muted-foreground text-sm', className)}
    data-slot="card-description"
    {...restProps}>
    {@render children?.()}
</p>
