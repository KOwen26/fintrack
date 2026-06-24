<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { Select as SelectPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: ComponentProps<typeof SelectPrimitive.GroupHeading> = $props();
</script>

<SelectPrimitive.GroupHeading
    data-slot="select-group-heading"
    class={cn('text-muted-foreground px-2 py-1.5 text-xs', className)}
    bind:ref
    {...restProps}>
    {@render children?.()}
</SelectPrimitive.GroupHeading>
