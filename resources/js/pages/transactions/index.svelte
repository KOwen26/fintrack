<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';

    interface PaginatedTransactions {
        data: App.Models.Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        transactions,
    }: {
        transactions: PaginatedTransactions;
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
    <TransactionList transactions={transactions.data} />

    {#if transactions.last_page > 1}
        <div class="mt-4 flex flex-wrap items-center justify-center gap-1">
            {#each transactions.links as link (link.label)}
                {#if link.url}
                    <Link
                        class="btn btn-xs {link.active ? 'btn-primary' : 'btn-ghost'}"
                        href={link.url}
                        preserveScroll>
                        {link.label}
                    </Link>
                {:else}
                    <span class="btn btn-disabled btn-xs">{link.label}</span>
                {/if}
            {/each}
        </div>
    {/if}
</PageSection>
