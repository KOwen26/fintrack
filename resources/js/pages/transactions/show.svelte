<script lang="ts">
    import type { Data } from '@type/type';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionDetail from '@components/module/transaction/transaction-detail.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { transaction }: { transaction: Data.TransactionDetailData } = $props();

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(TransactionController.destroy.url({ transaction: transaction.id }));
    }

    const isTransferRow = $derived(
        transaction.type === 'transfer_out' ||
            transaction.type === 'transfer_in' ||
            transaction.type === 'fee'
    );
</script>

<DashboardPageHeader title="">
    <div class="space-y-1">
        <h1 class="text-xl font-bold">Transaction Details</h1>
        <div class="flex items-center gap-1.5">
            <TransactionTypeBadge type={transaction.type} />
            {#if isTransferRow}
                <span class="text-xs text-base-content/50">Transfer</span>
            {/if}
        </div>
    </div>

    {#snippet actions()}
        <Button
            color="light"
            // href={TransactionController.edit.url({ transaction: transaction.id })}
            variant="outline">
            <i class="iconify size-5 ph--pencil-simple-bold"></i>
            Edit
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection breakMargin>
    <TransactionDetail {transaction} />

    <!-- Action Buttons -->
    <div class="flex gap-3 mt-4">
        <Button
            class="flex-1"
            color="light"
            // href={TransactionController.edit.url({ transaction: transaction.id })}
            variant="outline">
            <i class="iconify size-4 ph--pencil-simple-bold"></i>
            Edit
        </Button>
        <Button
            class="flex-1"
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="outline">
            <i class="iconify size-4 ph--trash-bold"></i>
            Delete
        </Button>
    </div>
</PageSection>

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
