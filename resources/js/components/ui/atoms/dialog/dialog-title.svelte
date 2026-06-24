<script lang="ts">
    import { Dialog as DialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: DialogPrimitive.TitleProps = $props();
</script>

<DialogPrimitive.Title
    data-slot="dialog-title"
    class={cn('text-lg leading-none font-semibold', className)}
    bind:ref
    {...restProps} />
