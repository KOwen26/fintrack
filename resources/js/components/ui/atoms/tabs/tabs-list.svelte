<script lang="ts">
    import { Tabs as TabsPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: TabsPrimitive.ListProps = $props();
</script>

<TabsPrimitive.List
    data-slot="tabs-list"
    class={cn(
        'inline-flex h-9 w-fit items-center justify-center rounded-lg bg-muted p-1 text-muted-foreground',
        className
    )}
    bind:ref
    {...restProps} />
