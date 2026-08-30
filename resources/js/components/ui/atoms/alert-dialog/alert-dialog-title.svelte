<script lang="ts">
    import { AlertDialog as AlertDialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: AlertDialogPrimitive.TitleProps = $props();
</script>

<AlertDialogPrimitive.Title
    data-slot="alert-dialog-title"
    class={cn('text-lg font-semibold', className)}
    bind:ref
    {...restProps} />
