<script lang="ts">
    import type { Data } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        transaction,
        accounts = [],
        categories = [],
    }: {
        transaction: Data.TransactionFormData;
        accounts?: App.Models.Account[];
        categories?: App.Models.Category[];
    } = $props();

    // The DTO collapses both edit shapes — a plain row and a transfer unit —
    // into one payload; `transaction.id` is always the source (outflow) row id,
    // which doubles as the delete target for both variants.
    const isTransfer = $derived(transaction.type === TransactionType.Transfer);

    let showDeleteConfirm = $state(false);

    // Deleting any unit member deletes the whole unit; the plain variant
    // deletes just the row.
    function destroy(): void {
        if (transaction.id === null) {
            return;
        }

        router.delete(TransactionController.destroy.url({ transaction: transaction.id }));
    }

    const backUrl = TransactionController.index.url();
</script>

<HeaderContext>
    <div class="flex w-full items-center justify-between gap-3">
        <Button size="icon" aria-label="Back to transactions" href={backUrl} variant="ghost">
            <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
        </Button>

        <h1 class="grow text-center font-medium text-primary">
            {isTransfer ? 'Edit Transfer' : 'Edit Transaction'}
        </h1>

        <Button
            size="icon"
            aria-label={isTransfer ? 'Delete transfer' : 'Delete transaction'}
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="ghost">
            <i class="iconify size-6 solar--trash-bin-2-line-duotone"></i>
        </Button>
    </div>
</HeaderContext>

<TransactionForm {accounts} {categories} {transaction} />

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title={isTransfer ? 'Delete Transfer' : 'Delete Transaction'}
    bind:open={showDeleteConfirm}>
    {#if isTransfer}
        This is part of a transfer. Deleting it will soft-delete all linked transfer rows.
    {:else}
        This transaction will be soft-deleted and cannot be recovered from the UI.
    {/if}
</ConfirmationModal>
