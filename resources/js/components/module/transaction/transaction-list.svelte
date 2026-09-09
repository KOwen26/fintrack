<script lang="ts" module>
    import type { SvelteTable } from '@tanstack/svelte-table';
    import type { App } from '@wayfinder/types';

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

    export type TransactionListTable = SvelteTable<TransactionListFeatures, App.Models.Transaction>;
</script>

<script lang="ts">
    import type { ColumnDef } from '@tanstack/svelte-table';
    import type { RestProps } from '@type/index';

    import { createTable } from '@tanstack/svelte-table';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { SvelteMap } from 'svelte/reactivity';

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

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transactions: App.Models.Transaction[];
        class?: string;
    }

    let { transactions, class: _class }: Props = $props();

    const columns: ColumnDef<typeof transactionListFeatures, App.Models.Transaction>[] = [
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
            accessorKey: 'type',
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

    const groupedTransactions = $derived.by<[string, App.Models.Transaction[]][]>(() => {
        const groups = new SvelteMap<string, App.Models.Transaction[]>();

        for (const row of table.getRowModel().rows) {
            const transaction = row.original;
            const group = groups.get(transaction.transaction_date);

            if (group) group.push(transaction);
            else groups.set(transaction.transaction_date, [transaction]);
        }

        return [...groups.entries()];
    });

    /* ── Summary ─────────────────────────────────────────── */

    const summary = $derived.by(() => {
        let income = 0;
        let expense = 0;

        for (const transaction of filteredTransactions) {
            if (
                transaction.type === TransactionType.Income ||
                transaction.type === TransactionType.TransferIn
            )
                income += transaction.amount;
            else if (
                transaction.type === TransactionType.Expense ||
                transaction.type === TransactionType.Fee
            )
                expense += transaction.amount;
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
    <!-- ── SEARCH + FILTERS ────────────────────────────────── -->
    <TransactionListFilter {table} {transactions} />

    <!-- ── SUMMARY STRIP ───────────────────────────────────── -->
    {#if transactions.length > 0}
        {@render Summary()}
    {/if}

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
        <div class="flex flex-col">
            {#each groupedTransactions as [date, txns] (date)}
                <!-- Day header -->
                <div class="group mb-2">
                    <div class="flex items-center justify-between px-1 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-base-content/60"
                                >{DateTimeHelper.format(date, 'day-date')}</span>
                            <span
                                class="rounded-full bg-base-content/10 px-2 py-0.5 text-2xs font-semibold text-base-content/40">
                                {txns.length} transactions
                            </span>
                        </div>
                    </div>

                    <!-- Group card -->
                    <div class="overflow-hidden rounded-2xl bg-base-100 shadow-xs">
                        {#each txns as txn (txn.id)}
                            <TransactionListItem transaction={txn} />
                        {/each}
                    </div>
                </div>
            {/each}
        </div>

        <!-- Load more -->
        {#if !allLoaded}
            <button
                class="mx-0 mt-2 mb-0 flex w-full items-center justify-center gap-2 rounded-2xl bg-base-100 px-4 py-3.5 font-sans text-sm font-semibold text-primary shadow-xs transition-colors duration-150 hover:bg-primary/10"
                onclick={loadMore}>
                <i class="iconify size-3.5 solar--alt-arrow-down-line-duotone"></i>
                Load more
            </button>
        {:else}
            <p class="py-3 text-center text-xs text-base-content/40">All transactions are shown</p>
        {/if}
    {/if}
</div>

{#snippet Summary()}
    <div class="overflow-hidden rounded-2xl bg-base-100 shadow-xs">
        <div class="flex">
            <div class="flex-1 space-y-1 px-4 py-3.5">
                <div
                    class="flex items-center gap-1 text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                    <i class="iconify size-3 text-success solar--arrow-up-line-duotone"></i>
                    Income
                </div>
                <div class="font-mono text-sm font-medium text-success">
                    {#if summary.income > 0}
                        {Formatter.currency(summary.income)}
                    {:else}
                        <i class="iconify size-3.5 text-base-content/25 solar--minus-line-duotone"
                        ></i>
                    {/if}
                </div>
            </div>
            <div class="flex-1 space-y-1 border-l border-base-content/10 px-4 py-3.5">
                <div
                    class="flex items-center gap-1 text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                    <i class="iconify size-3 text-error solar--arrow-down-line-duotone"></i>
                    Expense
                </div>
                <div class="font-mono text-sm font-medium text-error">
                    {#if summary.expense > 0}
                        {Formatter.currency(summary.expense)}
                    {:else}
                        <i class="iconify size-3.5 text-base-content/25 solar--minus-line-duotone"
                        ></i>
                    {/if}
                </div>
            </div>
            <div class="flex-1 space-y-1 border-l border-base-content/10 px-4 py-3.5">
                <div class="text-2xs font-bold tracking-wider text-base-content/40 uppercase">
                    <i class="iconify size-3 solar--transfer-vertical-line-duotone"></i>
                    Net
                </div>
                <div
                    class="font-mono text-sm font-medium"
                    class:text-error={summary.net < 0}
                    class:text-success={summary.net >= 0}>
                    {#if summary.net !== 0}
                        <i
                            class={cn(
                                'iconify size-3.5',
                                summary.net > 0
                                    ? 'solar--add-bold-duotone'
                                    : 'solar--minus-bold-duotone'
                            )}></i>
                        {Formatter.currency(Math.abs(summary.net))}
                    {:else}
                        <i class="iconify size-3.5 text-base-content/25 solar--minus-line-duotone"
                        ></i>
                    {/if}
                </div>
            </div>
        </div>
    </div>
{/snippet}
