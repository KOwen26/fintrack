<script lang="ts">
    import type { TransactionListData } from '@type/generated';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TransactionListFilter, {
        SORT_STATE,
        toSortKey,
    } from '@components/module/transaction/transaction-list-filter.svelte';
    import TransactionList, {
        createTransactionListTable,
    } from '@components/module/transaction/transaction-list.svelte';
    import TransactionSummaryCard from '@components/module/transaction/transaction-summary-card.svelte';

    let {
        transactions,
    }: {
        transactions: TransactionListData[];
    } = $props();

    /* ── Table (filtering + sorting brain) ───────────────── */

    const table = createTransactionListTable(() => transactions);

    const filteredTransactions = $derived(
        table.getFilteredRowModel().rows.map((row) => row.original)
    );

    /* ── Summary over the filtered set ───────────────────── */

    const summary = $derived.by(() => {
        let income = 0;
        let expense = 0;

        for (const transaction of filteredTransactions) {
            if (transaction.type === 'income') income += transaction.amount;
            else if (transaction.type === 'expense') expense += transaction.amount;
        }

        return { income, expense, net: income - expense };
    });

    /* ── Active filter state ─────────────────────────────── */

    const hasActiveFilters = $derived(
        table.atoms.columnFilters.get().length > 0 ||
            Boolean(table.atoms.globalFilter.get()) ||
            toSortKey(table.atoms.sorting.get()) !== 'newest'
    );

    function resetAllFilters() {
        table.resetGlobalFilter();
        table.resetColumnFilters();
        table.setSorting(SORT_STATE.newest);
    }

    function loadMoreAction() {
        router.reload({ only: ['transactions'] });
    }
</script>

<MobilePageLayout contentClass="[timeline-scope:--filter-stuck]" variant="4/5">
    {#snippet hero()}
        <!-- ── SUMMARY CARD ────────────────────────────────────── -->
        {#if transactions.length > 0}
            <TransactionSummaryCard {summary} />
        {/if}
    {/snippet}

    <!-- ── SEARCH + FILTERS (sticky in content) ───────────── -->
    <!-- Non-sticky sentinel: Chromium's view() timelines don't advance for
         position:sticky subjects, so this 1px marker (invisible to layout)
         crosses the scrollport top exactly when the bar sticks. -->
    <div class="filter-sentinel" aria-hidden="true"></div>
    <div
        class="sticky-filter sticky top-0 z-20 -mx-5 border-b border-transparent bg-base-100 px-5 py-2.5">
        <TransactionListFilter {table} {transactions} />
    </div>

    <!-- ── TRANSACTION LIST ────────────────────────────────── -->
    {#if filteredTransactions.length === 0}
        {#if hasActiveFilters}
            <EmptyItemPlaceholder
                ctaLabel="Reset all filters"
                ctaOnclick={resetAllFilters}
                icon="solar--magnifer-bold-duotone"
                label="No transactions match the selected filters." />
        {:else}
            <EmptyItemPlaceholder
                ctaLabel="Add transaction"
                ctaUrl={TransactionController.create.url()}
                icon="solar--magnifer-bold-duotone"
                label="No transactions" />
        {/if}
    {:else}
        <TransactionList {loadMoreAction} transactions={filteredTransactions} />
    {/if}
</MobilePageLayout>

<style>
    /* Scroll-driven "stuck" styling: the view() timeline tracks this element
       through the scrollport; `contain 99% -> 100%` ends exactly at the stick
       moment, and while stuck the timeline freezes there, holding the styles.
       Browsers without scroll-driven animations simply keep the bar flat. */
    .filter-sentinel {
        height: 1px;
        margin-bottom: -1px;
        view-timeline: --filter-stuck;
    }

    .sticky-filter {
        animation: sticky-filter-stuck linear both;
        animation-timeline: --filter-stuck;
        animation-range-start: exit 0%;
        animation-range-end: exit 100%;
    }

    @keyframes sticky-filter-stuck {
        to {
            border-color: var(--color-base-300);
            box-shadow:
                0 1px 3px 0 rgb(0 0 0 / 0.1),
                0 1px 2px -1px rgb(0 0 0 / 0.1);
        }
    }
</style>
