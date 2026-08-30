<script lang="ts">
    import { Tabs as TabsPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        value = $bindable(''),
        class: className,
        ...restProps
    }: TabsPrimitive.RootProps = $props();
</script>

<TabsPrimitive.Root
    data-slot="tabs"
    class={cn('flex flex-col gap-2', className)}
    bind:ref
    bind:value
    {...restProps} />
