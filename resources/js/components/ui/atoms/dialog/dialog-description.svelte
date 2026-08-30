<script lang="ts">
    import { Dialog as DialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: DialogPrimitive.DescriptionProps = $props();
</script>

<DialogPrimitive.Description
    data-slot="dialog-description"
    class={cn('text-muted-foreground text-sm', className)}
    bind:ref
    {...restProps} />
