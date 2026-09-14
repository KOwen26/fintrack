<script lang="ts">
    import type { TransactionListData } from '@type/generated';

    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        transactions,
    }: {
        transactions: TransactionListData[];
    } = $props();
</script>

<DashboardPageHeader title="Transactions">
    {#snippet actions()}
        <Button color="primary" href={TransactionController.create.url()}>
            <i class="iconify size-5 solar--add-bold-duotone"></i>
            Add
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection>
    <TransactionList {transactions} />
</PageSection>
