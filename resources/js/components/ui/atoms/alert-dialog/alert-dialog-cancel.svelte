<script lang="ts">
    import { AlertDialog as AlertDialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: AlertDialogPrimitive.CancelProps = $props();
</script>

<AlertDialogPrimitive.Cancel
    data-slot="alert-dialog-cancel"
    class={cn(buttonVariants({ variant: 'outline', color: 'light' }), className)}
    bind:ref
    {...restProps} />
