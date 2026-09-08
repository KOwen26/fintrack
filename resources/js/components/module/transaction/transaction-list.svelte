<script lang="ts">
    import type { TransactionListFilters } from '@components/module/transaction/transaction-list-filter.svelte';
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import TransactionListFilter from '@components/module/transaction/transaction-list-filter.svelte';
    import TransactionListItem from '@components/module/transaction/transaction-list-item.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transactions: App.Models.Transaction[];
        class?: string;
    }

    let { transactions, class: _class }: Props = $props();

    /* ── Filters (owned here, mutated via TransactionListFilter) ── */

    let filters = $state<TransactionListFilters>({
        search: '',
        accountIds: [],
        categoryIds: [],
        types: [],
        sort: 'newest',
    });

    /* ── Derived filtered list ───────────────────────────── */

    const filteredTransactions = $derived.by<App.Models.Transaction[]>(() => {
        let list = [...transactions];

        const q = filters.search.trim().toLowerCase();
        if (q) {
            list = list.filter(
                (t) =>
                    t.description.toLowerCase().includes(q) ||
                    t.category.name.toLowerCase().includes(q) ||
                    t.account.name.toLowerCase().includes(q)
            );
        }

        if (filters.accountIds.length) {
            list = list.filter((t) => filters.accountIds.includes(t.account.id));
        }
        if (filters.categoryIds.length) {
            list = list.filter((t) => filters.categoryIds.includes(t.category.id));
        }
        if (filters.types.length) {
            list = list.filter((t) => filters.types.includes(t.type));
        }

        list.sort((a, b) => {
            if (filters.sort === 'newest')
                return b.transaction_date.localeCompare(a.transaction_date) || b.id - a.id;
            if (filters.sort === 'oldest')
                return a.transaction_date.localeCompare(b.transaction_date) || a.id - b.id;
            if (filters.sort === 'highest') return b.amount - a.amount;
            if (filters.sort === 'lowest') return a.amount - b.amount;

            return 0;
        });

        return list;
    });

    /* ── Group by date ───────────────────────────────────── */

    const groupedTransactions = $derived.by<[string, App.Models.Transaction[]][]>(() => {
        const map = new SvelteMap<string, App.Models.Transaction[]>();
        for (const t of filteredTransactions) {
            const g = map.get(t.transaction_date);
            if (g) g.push(t);
            else map.set(t.transaction_date, [t]);
        }
        const dates = [...map.keys()];
        if (filters.sort === 'oldest') dates.sort();
        else dates.sort().reverse();

        return dates.map((d) => [d, map.get(d)!] as [string, App.Models.Transaction[]]);
    });

    /* ── Summary ─────────────────────────────────────────── */

    const summary = $derived.by(() => {
        let inc = 0,
            exp = 0;
        for (const t of filteredTransactions) {
            if (t.type === TransactionType.Income || t.type === TransactionType.TransferIn)
                inc += t.amount;
            else if (t.type === TransactionType.Expense || t.type === TransactionType.Fee)
                exp += t.amount;
        }
        const net = inc - exp;

        return { income: inc, expense: exp, net };
    });

    /* ── Active filter state ─────────────────────────────── */

    const hasActiveFilters = $derived(
        filters.accountIds.length > 0 ||
            filters.categoryIds.length > 0 ||
            filters.types.length > 0 ||
            filters.sort !== 'newest'
    );

    /* ── Reset ───────────────────────────────────────────── */

    function resetAllFilters() {
        filters.search = '';
        filters.accountIds = [];
        filters.categoryIds = [];
        filters.types = [];
        filters.sort = 'newest';
    }

    /* ── Load more ───────────────────────────────────────── */

    let allLoaded = $state(false);

    function loadMore() {
        allLoaded = true;
    }
</script>

<div class={cn('flex flex-col gap-3', _class)}>
    <!-- ── SEARCH + FILTERS ────────────────────────────────── -->
    <TransactionListFilter {transactions} bind:filters />

    <!-- ── SUMMARY STRIP ───────────────────────────────────── -->
    {#if transactions.length > 0}
        {@render Summary()}
    {/if}

    <!-- ── TRANSACTION LIST ────────────────────────────────── -->
    {#if filteredTransactions.length === 0}
        <!-- Empty state -->
        {#if hasActiveFilters || filters.search}
            <EmptyItemPlaceholder
                ctaLabel="Reset semua filter"
                ctaOnclick={resetAllFilters}
                icon="solar--magnifer-bold-duotone"
                label="Tidak ada transaksi yang cocok dengan filter yang dipilih." />
        {:else}
            <EmptyItemPlaceholder
                ctaLabel="Tambah transaksi"
                ctaUrl={TransactionController.create.url()}
                icon="solar--magnifer-bold-duotone"
                label="Tidak ada transaksi" />
        {/if}
    {:else}
        <div class="flex flex-col">
            {#each groupedTransactions as [date, txns], i (i)}
                <!-- Day header -->
                <div class="group mb-2">
                    <div class="flex items-center justify-between px-1 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-base-content/60"
                                >{DateTimeHelper.format(date, 'day-date')}</span>
                            <span
                                class="rounded-full bg-base-content/10 px-2 py-0.5 text-2xs font-semibold text-base-content/40">
                                {txns.length} transaksi
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
                Muat lebih banyak
            </button>
        {:else}
            <p class="py-3 text-center text-xs text-base-content/40">
                Semua transaksi sudah ditampilkan
            </p>
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
                    Masuk
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
                    Keluar
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
                    class:font-medium={true}
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
