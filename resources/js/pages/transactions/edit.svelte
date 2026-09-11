<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import TransferForm from '@components/module/transaction/transfer-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        transaction,
        transfer,
        accounts = [],
        categories,
    }: {
        transaction?: App.Models.Transaction;
        transfer?: App.Models.Transfer & { transactions: App.Models.Transaction[] };
        accounts?: App.Models.Account[];
        categories: App.Models.Category[];
    } = $props();

    // The page serves two edit shapes: a plain row (transaction prop, from
    // transactions/{id}/edit) or a transfer unit (transfer prop, from
    // transfers/{transfer}/edit) — the form switches on which is present.
    const isUnitEdit = $derived(transfer !== undefined);

    const badgeType = $derived(
        isUnitEdit ? ('transfer' as const) : (transaction?.type ?? ('expense' as const))
    );

    const sourceTransaction = $derived(
        transfer?.transactions.find((row) => row.type === 'transfer' && row.flow === 'outflow')
    );

    let showDeleteConfirm = $state(false);

    // Deleting any unit member deletes the whole unit; the plain variant
    // deletes just the row.
    function destroy(): void {
        const id = isUnitEdit ? sourceTransaction?.id : transaction?.id;

        if (id === undefined) {
            return;
        }

        router.delete(TransactionController.destroy.url({ transaction: id }));
    }

    function onCancel(): void {
        router.visit(TransactionController.index.url());
    }
</script>

<DashboardPageHeader title="">
    <div class="space-y-1">
        <h1 class="text-xl font-bold">Edit Transaction</h1>
        <div class="flex items-center gap-1.5">
            <TransactionTypeBadge type={badgeType} />
            {#if isUnitEdit}
                <span class="text-xs text-base-content/50">Transfer — edit the whole unit</span>
            {/if}
        </div>
    </div>
</DashboardPageHeader>

<PageSection>
    {#if isUnitEdit && transfer}
        <TransferForm {accounts} {onCancel} {transfer} />
    {:else if transaction}
        <TransactionForm {accounts} {categories} {onCancel} {transaction} />
    {/if}

    <div class="mt-4">
        <Button
            class="w-full"
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="outline">
            <i class="iconify size-4 solar--trash-bin-2-bold-duotone"></i>
            {isUnitEdit ? 'Delete Transfer (all linked rows)' : 'Delete Transaction'}
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
    {#if isUnitEdit}
        This is part of a transfer. Deleting it will soft-delete all linked transfer rows.
    {:else}
        This transaction will be soft-deleted and cannot be recovered from the UI.
    {/if}
</ConfirmationModal>
