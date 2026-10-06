<script lang="ts">
    import { Separator as SeparatorPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn';

    let {
        ref = $bindable(null),
        class: className,
        'data-slot': dataSlot = 'separator',
        ...restProps
    }: SeparatorPrimitive.RootProps = $props();
</script>

<SeparatorPrimitive.Root
    data-slot={dataSlot}
    class={cn(
        'shrink-0 bg-border data-horizontal:h-px data-horizontal:w-full data-vertical:w-px data-vertical:self-stretch',
        className
    )}
    bind:ref
    {...restProps} />
