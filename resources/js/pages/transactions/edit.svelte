<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        account,
        transaction,
        categories,
    }: {
        account: App.Models.Account;
        transaction: App.Models.Transaction;
        categories: App.Models.Category[];
    } = $props();

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(
            TransactionController.destroy.url({ account: account.id, transaction: transaction.id })
        );
    }

    // Transfer rows cannot have their type changed — show a read-only badge instead of the type select
    const isTransferRow = $derived(
        transaction.type === 'transfer_out' ||
            transaction.type === 'transfer_in' ||
            transaction.type === 'fee'
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={TransactionController.index.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">Edit Transaction</h1>
            <div class="mt-1">
                <TransactionTypeBadge type={transaction.type} />
                {#if isTransferRow}
                    <span class="ml-1 text-xs text-base-content/50"
                        >Transfer — type cannot be changed</span>
                {/if}
            </div>
        </div>
    </div>

    <TransactionForm
        {account}
        {categories}
        onCancel={() => router.visit(TransactionController.index.url({ account: account.id }))}
        {transaction} />

    <div class="mt-4">
        <Button
            class="w-full"
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="outline">
            <i class="iconify size-4 ph--trash-bold"></i>
            {isTransferRow ? 'Delete Transfer (all linked rows)' : 'Delete Transaction'}
        </Button>
    </div>
</div>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title="Delete Transaction"
    bind:open={showDeleteConfirm}>
    {#if isTransferRow}
        This is part of a transfer. Deleting it will soft-delete all linked transfer rows.
    {:else}
        This transaction will be soft-deleted and cannot be recovered from the UI.
    {/if}
</ConfirmationModal>
