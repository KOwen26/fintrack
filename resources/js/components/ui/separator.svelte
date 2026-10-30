<script lang="ts">
    import { Separator as SeparatorPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn';

    let {
        ref = $bindable(null),
        class: className,
        variant = 'default',
        'data-slot': dataSlot = 'separator',
        ...restProps
    }: SeparatorPrimitive.RootProps & { variant?: 'default' | 'thick' } = $props();
</script>

<SeparatorPrimitive.Root
    data-slot={dataSlot}
    class={cn(
        'shrink-0 bg-border data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:w-px data-[orientation=vertical]:self-stretch',
        variant === 'thick' &&
            'data-[orientation=horizontal]:h-2.5 data-[orientation=vertical]:w-2.5',
        className
    )}
    bind:ref
    {...restProps} />
