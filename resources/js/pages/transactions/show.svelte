<script lang="ts">
    import type { Data } from '@type/type';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import TransferController from '@wayfinder/App/Http/Controllers/TransferController';

    import TransactionDetail from '@components/module/transaction/transaction-detail.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { transaction }: { transaction: Data.TransactionDetailData } = $props();

    let showDeleteConfirm = $state(false);

    const isTransferRow = $derived(transaction.type === 'transfer');

    const editHref = $derived(
        transaction.transfer_id !== null
            ? TransferController.edit.url({ transfer: transaction.transfer_id })
            : TransactionController.edit.url({ transaction: transaction.id })
    );

    function destroy(): void {
        router.delete(TransactionController.destroy.url({ transaction: transaction.id }));
    }

    const backUrl = TransactionController.index.url();
</script>

<HeaderContext>
    <div class="flex w-full items-center justify-between gap-3">
        <Button
            class="size-10 shrink-0 p-1 btn-sm"
            aria-label="Back to transactions"
            href={backUrl}
            variant="ghost">
            <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
        </Button>

        <Button
            class="size-10 shrink-0 p-1 btn-sm"
            aria-label={isTransferRow ? 'Delete transfer' : 'Delete transaction'}
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="ghost">
            <i class="iconify size-6 solar--trash-bin-2-line-duotone"></i>
        </Button>
    </div>
</HeaderContext>

<TransactionDetail {transaction} />

<BottomActionBar>
    <Button class="w-full grow" color="primary" href={editHref}>
        <i class="iconify size-5 solar--pen-line-duotone"></i>
        Edit {isTransferRow ? 'Transfer' : 'Transaction'}
    </Button>
</BottomActionBar>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title={isTransferRow ? 'Delete Transfer' : 'Delete Transaction'}
    bind:open={showDeleteConfirm}>
    {#if isTransferRow}
        This is part of a transfer. Deleting it will soft-delete all linked transfer rows.
    {:else}
        This transaction will be soft-deleted and cannot be recovered from the UI.
    {/if}
</ConfirmationModal>
