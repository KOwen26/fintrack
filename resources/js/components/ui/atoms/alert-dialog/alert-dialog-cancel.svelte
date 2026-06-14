<script lang="ts">
    import { AlertDialog as AlertDialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { tvButtonVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: AlertDialogPrimitive.CancelProps = $props();
</script>

<AlertDialogPrimitive.Cancel
    class={cn(buttonVariants({ variant: 'outline' }), className)}
    data-slot="alert-dialog-cancel"
    bind:ref
    {...restProps} />
