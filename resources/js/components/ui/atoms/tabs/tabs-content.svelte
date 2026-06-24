<script lang="ts">
    import { Tabs as TabsPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: TabsPrimitive.ContentProps = $props();
</script>

<TabsPrimitive.Content
    data-slot="tabs-content"
    class={cn('flex-1 outline-none', className)}
    bind:ref
    {...restProps} />
