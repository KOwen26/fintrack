<script lang="ts" module>
    import type { ColumnDef, SvelteTable } from '@tanstack/svelte-table';
    import type { Data } from '@type/type';

    import { FILTER_COLUMNS } from './transaction-list-filter.svelte';

    import {
        columnFilteringFeature,
        createFilteredRowModel,
        createSortedRowModel,
        createTable,
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

    const transactionListColumns: ColumnDef<TransactionListFeatures, Data.TransactionListData>[] = [
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
            accessorFn: (row) => row.type,
            filterFn: 'arrayHas',
            enableGlobalFilter: false,
        },
    ];

    /**
     * Creates the filterable/sortable table that drives the transaction list.
     * The page owns the instance; the list component only renders rows.
     */
    export function createTransactionListTable(
        transactions: () => Data.TransactionListData[]
    ): TransactionListTable {
        return createTable({
            features: transactionListFeatures,
            columns: transactionListColumns,
            get data() {
                return transactions();
            },
            getRowId: (row) => String(row.id),
        });
    }
</script>

<script lang="ts">
    import type { RestProps } from '@type/index';

    import TransactionListItem from './transaction-list-item.svelte';

    import { Collapsible } from 'bits-ui';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import { cn } from '@utilities/shadcn';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import Button from '@components/ui/button.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        /** Pre-filtered, flat transaction rows — the page owns filtering. */
        transactions: Data.TransactionListData[];
        class?: string;
        hideTotal?: boolean;
        hideControl?: boolean;
        loadMoreAction?: () => void;
    }

    let {
        transactions,
        class: _class,
        hideTotal = false,
        hideControl = false,
        loadMoreAction,
    }: Props = $props();

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
            if (transaction.type === 'income') net += transaction.amount;
            else if (transaction.type === 'expense') net -= transaction.amount;
        }

        return net;
    }

    const groupedTransactions = $derived.by<DayGroup[]>(() => {
        const groups = new SvelteMap<string, Data.TransactionListData[]>();

        for (const transaction of transactions) {
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

    /* ── Count ───────────────────────────────────────────── */

    const filteredCount = $derived(transactions.length);

    /* ── Load more ───────────────────────────────────────── */
</script>

<div class={cn('flex flex-col gap-3', _class)}>
    {#if !hideTotal}
        <!-- Count header -->
        <p class="mx-0.5 mt-1 mb-0 text-sm text-base-content/60">
            {filteredCount}
            transactions
        </p>
    {/if}

    <div class="flex flex-col gap-2">
        {#each groupedTransactions as group, i (group.date)}
            <Collapsible.Root
                class="overflow-hidden rounded-lg bg-base-200 shadow-xs"
                onOpenChange={(open) => dayOpenOverrides.set(group.date, open)}
                open={dayOpenOverrides.get(group.date) ??
                    i <= Math.ceil(groupedTransactions.length / 3)}>
                <!-- Day header (trigger) -->
                <Collapsible.Trigger
                    class="flex w-full cursor-pointer items-center gap-2 p-3 text-left select-none">
                    <span class="text-xs font-semibold text-base-content uppercase">
                        {DateTimeHelper.format(group.date, 'date')}
                    </span>

                    <span
                        class={cn(
                            'ml-auto flex items-center text-sm font-semibold whitespace-nowrap',
                            group.net > 0
                                ? 'text-success'
                                : group.net < 0
                                  ? 'text-error'
                                  : 'text-base-content'
                        )}>
                        <CurrencyAmount value={group.net} />
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

    {#if !hideControl}
        <!-- Load more -->
        {#if loadMoreAction}
            <Button class="mt-2 w-full" color="light" onclick={loadMoreAction} variant="soft">
                <i class="iconify size-3.5 solar--alt-arrow-down-line-duotone"></i>
                Load more
            </Button>
        {/if}
    {/if}
</div>
