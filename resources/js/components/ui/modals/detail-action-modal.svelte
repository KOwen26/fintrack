<script lang="ts">
    import type { AlertModalProps } from './alert-modal.svelte';
    import type { ButtonProps } from '@components/ui/button.svelte';
    import type { Snippet } from 'svelte';

    import AlertModal from './alert-modal.svelte';

    import Button from '@components/ui/button.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    type Mode = 'view' | 'edit';

    interface DetailActionModalProps extends Omit<
        AlertModalProps,
        'footer' | 'actionButton' | 'children'
    > {
        action: () => void | Promise<void>;
        onCancel?: () => void;
        onSubmit?: () => void;
        onEdit?: () => void;

        children?: Snippet<[mode: Mode]>;
        mode?: Mode;

        editText?: string;
        closeText?: string;
        submitText?: string;
        cancelText?: string;

        editButtonProps?: Partial<ButtonProps>;
        closeButtonProps?: Partial<ButtonProps>;
        submitButtonProps?: Partial<ButtonProps>;
        cancelButtonProps?: Partial<ButtonProps>;
    }

    let {
        open = $bindable(false),
        title = 'Detail',
        children,
        action,
        onCancel,
        onSubmit,
        onEdit,
        mode = $bindable('view'),
        editText = 'Ubah',
        closeText = 'Tutup',
        submitText = 'Simpan',
        cancelText = 'Batal',
        editButtonProps,
        closeButtonProps,
        submitButtonProps,
        cancelButtonProps,
        ...props
    }: DetailActionModalProps = $props();

    let loading = $state(false);

    function handleClose() {
        onCancel?.();
        open = false;
    }

    function handleEdit() {
        onEdit?.();
        mode = 'edit';
    }

    function handleCancelEdit() {
        mode = 'view';
    }
</script>

<AlertModal {title} bind:open {...props}>
    {@render children?.(mode)}

    {#snippet footer()}
        {#if mode === 'view'}
            <Button color="light" onclick={handleClose} variant="outline" {...closeButtonProps}>
                {closeText}
            </Button>
            <Button color="primary" onclick={handleEdit} {...editButtonProps}>
                {editText}
            </Button>
        {:else}
            <Button
                color="light"
                disabled={loading}
                onclick={handleCancelEdit}
                variant="outline"
                {...cancelButtonProps}>
                {cancelText}
            </Button>
            <SubmitButton onclick={onSubmit} submitting={loading} {...submitButtonProps}>
                {submitText}
            </SubmitButton>
        {/if}
    {/snippet}
</AlertModal>
