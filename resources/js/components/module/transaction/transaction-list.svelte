<script lang="ts" module>
    import type { SvelteTable } from '@tanstack/svelte-table';
    import type { Data } from '@type/type';

    import {
        columnFilteringFeature,
        createFilteredRowModel,
        createSortedRowModel,
        filterFn_arrHas,
        globalFilteringFeature,
        rowSortingFeature,
        tableFeatures,
    } from '@tanstack/svelte-table';

    export const transactionListFeatures = tableFeatures({
        rowSortingFeature,
        columnFilteringFeature,
        globalFilteringFeature,

        sortedRowModel: createSortedRowModel(),
        filteredRowModel: createFilteredRowModel(),

        filterFns: {
            arrayHas: filterFn_arrHas,
        },
    });

    export type TransactionListFeatures = typeof transactionListFeatures;

    export type TransactionListTable = SvelteTable<
        TransactionListFeatures,
        Data.TransactionListData
    >;
</script>

<script lang="ts">
    import type { ColumnDef } from '@tanstack/svelte-table';
    import type { RestProps } from '@type/index';

    import { createTable } from '@tanstack/svelte-table';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { Collapsible } from 'bits-ui';
    import { SvelteMap } from 'svelte/reactivity';

    import { resolveKind } from '@schema/transaction.schema';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import TransactionListFilter, {
        FILTER_COLUMNS,
        SORT_STATE,
        toSortKey,
    } from '@components/module/transaction/transaction-list-filter.svelte';
    import TransactionListItem from '@components/module/transaction/transaction-list-item.svelte';
    import TransactionSummaryCard from '@components/module/transaction/transaction-summary-card.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transactions: Data.TransactionListData[];
        class?: string;
    }

    let { transactions, class: _class }: Props = $props();

    const columns: ColumnDef<typeof transactionListFeatures, Data.TransactionListData>[] = [
        { accessorKey: 'id', enableGlobalFilter: false },
        { accessorKey: 'transaction_date', enableGlobalFilter: false },
        { accessorKey: 'amount', enableGlobalFilter: true },
        {
            id: 'description',
            accessorFn: (row) => row.description ?? '',
            enableGlobalFilter: true,
        },
        {
            id: 'category_name',
            accessorFn: (row) => row.category?.name ?? '',
            enableGlobalFilter: true,
        },
        {
            id: 'account_name',
            accessorFn: (row) => row.account?.name ?? '',
            enableGlobalFilter: true,
        },
        {
            id: FILTER_COLUMNS.account_id,
            accessorFn: (row) => row.account?.id,
            filterFn: 'arrayHas',
            enableGlobalFilter: false,
        },
        {
            id: FILTER_COLUMNS.category_id,
            accessorFn: (row) => row.category?.id,
            filterFn: 'arrayHas',
            enableGlobalFilter: false,
        },
        {
            id: 'type',
            accessorFn: (row) => resolveKind(row.type),
            filterFn: 'arrayHas',
            enableGlobalFilter: false,
        },
    ];

    const table = createTable({
        features: transactionListFeatures,
        columns,
        get data() {
            return transactions;
        },
        getRowId: (row) => String(row.id),
    });

    /* ── Derived views ───────────────────────────────────── */

    const filteredTransactions = $derived(
        table.getFilteredRowModel().rows.map((row) => row.original)
    );

    /* ── Group by date ───────────────────────────────────── */

    interface DayGroup {
        date: string;
        transactions: Data.TransactionListData[];
        net: number;
    }

    /** Inflows minus outflows for a set of transactions; transfers excluded. */
    function dayNet(transactions: Data.TransactionListData[]): number {
        let net = 0;

        for (const transaction of transactions) {
            const kind = resolveKind(transaction.type);

            if (kind === 'income') net += transaction.amount;
            else if (kind === 'expense') net -= transaction.amount;
        }

        return net;
    }

    const groupedTransactions = $derived.by<DayGroup[]>(() => {
        const groups = new SvelteMap<string, Data.TransactionListData[]>();

        for (const row of table.getRowModel().rows) {
            const transaction = row.original;
            const group = groups.get(transaction.transaction_date);

            if (group) group.push(transaction);
            else groups.set(transaction.transaction_date, [transaction]);
        }

        return [...groups.entries()].map(([date, txns]) => ({
            date,
            transactions: txns,
            net: dayNet(txns),
        }));
    });

    /* ── Day collapse state ──────────────────────────────── */

    // Explicit open/closed choices per day; untouched days fall back to the
    // default: the first group (today) open, all others collapsed.
    const dayOpenOverrides = new SvelteMap<string, boolean>();

    /* ── Summary ─────────────────────────────────────────── */

    const summary = $derived.by(() => {
        let income = 0;
        let expense = 0;

        for (const transaction of filteredTransactions) {
            const kind = resolveKind(transaction.type);

            if (kind === 'income') income += transaction.amount;
            else if (kind === 'expense') expense += transaction.amount;
        }

        return { income, expense, net: income - expense };
    });

    /* ── Active filter state ─────────────────────────────── */

    const hasActiveFilters = $derived(
        table.atoms.columnFilters.get().length > 0 ||
            Boolean(table.atoms.globalFilter.get()) ||
            toSortKey(table.atoms.sorting.get()) !== 'newest'
    );

    /* ── Reset ───────────────────────────────────────────── */

    function resetAllFilters() {
        table.resetGlobalFilter();
        table.resetColumnFilters();
        table.setSorting(SORT_STATE.newest);
    }

    /* ── Load more ───────────────────────────────────────── */

    let allLoaded = $state(false);

    function loadMore() {
        allLoaded = true;
    }
</script>

<div class={cn('flex flex-col gap-3', _class)}>
    <!-- ── SUMMARY CARD ────────────────────────────────────── -->
    {#if transactions.length > 0}
        <TransactionSummaryCard {summary} />
    {/if}

    <!-- ── SEARCH + FILTERS ────────────────────────────────── -->
    <TransactionListFilter {table} {transactions} />

    <!-- ── TRANSACTION LIST ────────────────────────────────── -->
    {#if filteredTransactions.length === 0}
        <!-- Empty state -->
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
        <!-- Count header -->
        <p class="mx-0.5 mt-1 mb-0 text-sm text-base-content/60">
            {filteredTransactions.length}
            transactions
        </p>

        <div class="flex flex-col gap-2">
            {#each groupedTransactions as group, i (group.date)}
                <Collapsible.Root
                    class="overflow-hidden rounded-lg bg-base-100 shadow-xs"
                    onOpenChange={(open) => dayOpenOverrides.set(group.date, open)}
                    open={dayOpenOverrides.get(group.date) ?? i === 0}>
                    <!-- Day header (trigger) -->
                    <Collapsible.Trigger
                        class="flex w-full cursor-pointer items-center gap-2 p-3 text-left select-none">
                        <span class="text-xs font-semibold text-base-content uppercase">
                            {DateTimeHelper.format(group.date, 'date')}
                        </span>

                        <span
                            class={cn(
                                'ml-auto flex items-center font-mono text-sm font-semibold whitespace-nowrap',
                                group.net > 0
                                    ? 'text-success'
                                    : group.net < 0
                                      ? 'text-error'
                                      : 'text-base-content'
                            )}>
                            {Formatter.currency(group.net)}
                        </span>
                        <i
                            class={cn(
                                'iconify size-4 shrink-0 text-base-content/40 transition-transform duration-150',
                                (dayOpenOverrides.get(group.date) ?? i === 0)
                                    ? 'solar--alt-arrow-up-line-duotone'
                                    : 'solar--alt-arrow-down-line-duotone'
                            )}></i>
                    </Collapsible.Trigger>

                    <!-- Group card -->
                    <Collapsible.Content>
                        <div class="border-t border-base-content/10">
                            {#each group.transactions as txn (txn.id)}
                                <TransactionListItem transaction={txn} />
                            {/each}
                        </div>
                    </Collapsible.Content>
                </Collapsible.Root>
            {/each}
        </div>

        <!-- Load more -->
        {#if !allLoaded}
            <button
                class="mx-0 mt-2 mb-0 flex w-full items-center justify-center gap-2 rounded-lg bg-base-100 p-3.5 font-sans text-sm font-semibold text-primary shadow-xs transition-colors duration-150 hover:bg-primary/10"
                onclick={loadMore}>
                <i class="iconify size-3.5 solar--alt-arrow-down-line-duotone"></i>
                Load more
            </button>
        {:else}
            <p class="py-3 text-center text-xs text-base-content/40">All transactions are shown</p>
        {/if}
    {/if}
</div>
