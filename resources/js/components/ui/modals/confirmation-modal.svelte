<script lang="ts">
    import type { AlertModalProps } from './alert-modal.svelte';
    import type { ButtonProps } from '@components/ui/button.svelte';

    import AlertModal from './alert-modal.svelte';

    import Button from '@components/ui/button.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    interface ConfirmationModalProps extends Omit<AlertModalProps, 'footer' | 'actionButton'> {
        onConfirm?: () => void;
        onCancel?: () => void;
        loading?: boolean;

        confirmText?: string;
        cancelText?: string;

        confirmButtonProps?: Partial<ButtonProps>;
        cancelButtonProps?: Partial<ButtonProps>;
    }

    let {
        open = $bindable(false),
        title = 'Konfirmasi',
        children,
        onConfirm,
        onCancel,
        loading = false,
        confirmText = 'Konfirmasi',
        cancelText = 'Batal',
        confirmButtonProps,
        cancelButtonProps,
        ...props
    }: ConfirmationModalProps = $props();

    function handleCancel() {
        onCancel?.();
        open = false;
    }
</script>

<AlertModal {title} bind:open {...props}>
    {@render children?.()}

    {#snippet footer()}
        <Button color="light" onclick={handleCancel} variant="outline" {...cancelButtonProps}>
            {cancelText}
        </Button>
        <SubmitButton
            onclick={onConfirm}
            submitting={loading}
            type="button"
            {...confirmButtonProps}>
            {confirmText}
        </SubmitButton>
    {/snippet}
</AlertModal>
