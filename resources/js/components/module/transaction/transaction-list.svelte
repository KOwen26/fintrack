<script lang="ts" module>
    import type { ColumnDef, SvelteTable } from '@tanstack/svelte-table';

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

    export type TransactionListTable = SvelteTable<TransactionListFeatures, TransactionListData>;

    const transactionListColumns: ColumnDef<TransactionListFeatures, TransactionListData>[] = [
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
        transactions: () => TransactionListData[]
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
    import type { TransactionListData } from '@type/generated';
    import type { RestProps } from '@type/index';

    import TransactionListItem from './transaction-list-item.svelte';

    import { Collapsible } from 'bits-ui';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import { cn } from '@utilities/shadcn';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import Button from '@components/ui/button.svelte';
    import Separator from '@components/ui/separator.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        /** Pre-filtered, flat transaction rows — the page owns filtering. */
        transactions: TransactionListData[];
        class?: string;
        hideTotal?: boolean;
        hideControl?: boolean;
        loadMoreAction?: () => void;
        variant?: 'collapsible' | 'flat';
    }

    let {
        transactions,
        class: _class,
        hideTotal = false,
        hideControl = false,
        loadMoreAction,
        variant = 'collapsible',
    }: Props = $props();

    /* ── Group by date ───────────────────────────────────── */

    interface DayGroup {
        date: string;
        transactions: TransactionListData[];
        net: number;
    }

    /** Inflows minus outflows for a set of transactions; transfers excluded. */
    function dayNet(transactions: TransactionListData[]): number {
        let net = 0;

        for (const transaction of transactions) {
            if (transaction.type === 'income') net += transaction.amount;
            else if (transaction.type === 'expense') net -= transaction.amount;
        }

        return net;
    }

    let visibleCount = $state(Math.max(1, Math.ceil(transactions.length * 0.25)));

    function handleShowMore() {
        visibleCount = Math.min(
            transactions.length,
            visibleCount + Math.ceil(transactions.length * 0.25)
        );
    }

    const visibleTransactions = $derived(transactions.slice(0, visibleCount));

    const groupedTransactions = $derived.by<DayGroup[]>(() => {
        const groups = new SvelteMap<string, TransactionListData[]>();
        for (const transaction of visibleTransactions) {
            const day = transaction.transaction_date.slice(0, 10);
            const group = groups.get(day);

            if (group) group.push(transaction);
            else groups.set(day, [transaction]);
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

    const filteredCount = $derived(transactions.length);
</script>

<div class={cn('flex flex-col gap-3', _class)}>
    {#if !hideTotal}
        <!-- Count header -->
        <p class="mx-0.5 mt-1 mb-0 text-sm text-foreground/70">
            {filteredCount}
            transactions
        </p>
    {/if}

    <div class="flex flex-col">
        {#each groupedTransactions as group, i (group.date)}
            {#if variant === 'collapsible'}
                <Collapsible.Root
                    onOpenChange={(open) => dayOpenOverrides.set(group.date, open)}
                    open={dayOpenOverrides.get(group.date) ??
                        i <= Math.ceil(groupedTransactions.length / 3)}>
                    <Collapsible.Trigger
                        class={cn(
                            'flex w-full cursor-pointer items-center gap-2 py-2 text-left select-none'
                        )}>
                        <i
                            class={cn(
                                'iconify size-4 shrink-0 text-foreground/70 transition-transform duration-150',
                                'solar--alt-arrow-down-line-duotone',
                                dayOpenOverrides.get(group.date) && 'rotate-180'
                            )}></i>

                        {@render groupTitle(group)}

                        {@render groupNet(group)}
                    </Collapsible.Trigger>

                    <Collapsible.Content>
                        {#each group.transactions as txn (txn.id)}
                            <TransactionListItem transaction={txn} />
                        {/each}
                    </Collapsible.Content>
                </Collapsible.Root>
            {:else}
                <div>
                    <div class="flex items-center gap-2 py-2">
                        {@render groupTitle(group)}

                        {@render groupNet(group)}
                    </div>
                    <div>
                        {#each group.transactions as txn (txn.id)}
                            <TransactionListItem transaction={txn} />
                        {/each}
                    </div>
                </div>
            {/if}

            <Separator class="mt-2 mb-4" />
        {/each}
    </div>

    {#if !hideControl}
        {#if visibleCount < transactions.length}
            <Button class="mt-2 w-full" color="light" onclick={handleShowMore} variant="soft">
                <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
                Show More ({visibleCount}/{transactions.length})
            </Button>
        {:else if loadMoreAction}
            <Button class="mt-2 w-full" color="light" onclick={loadMoreAction} variant="soft">
                <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
                Load more
            </Button>
        {/if}
    {/if}
</div>

{#snippet groupTitle(group)}
    <span class="text-xs font-semibold text-foreground uppercase">
        {DateTimeHelper.format(group.date, 'date')}
    </span>
{/snippet}

{#snippet groupNet(group)}
    {#if group.net != 0}
        <CurrencyAmount
            class={cn(
                'ml-auto text-sm font-semibold whitespace-nowrap text-foreground',
                group.net > 0 && 'text-success',
                group.net < 0 && 'text-error'
            )}
            value={group.net} />
    {/if}
{/snippet}
