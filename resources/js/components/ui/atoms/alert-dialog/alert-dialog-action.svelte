<script lang="ts">
    import { AlertDialog as AlertDialogPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: AlertDialogPrimitive.ActionProps = $props();
</script>

<AlertDialogPrimitive.Action
    data-slot="alert-dialog-action"
    class={cn(buttonVariants(), className)}
    bind:ref
    {...restProps} />
