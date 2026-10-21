import type {
    ColumnDef,
    ColumnFiltersState,
    SortingState,
    SvelteTable,
} from '@tanstack/svelte-table';
import type { TransactionListData } from '@type/generated';
import type { App } from '@wayfinder/types';

import { router } from '@inertiajs/svelte';
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
import DatePeriodPreset from '@wayfinder/App/Enums/DatePeriodPreset';
import { SvelteMap } from 'svelte/reactivity';

/* ── Filter/sort vocabulary ──────────────────────────── */

/** Column ids on the transaction list table that the filter UI drives. */
export const FILTER_COLUMNS = {
    account_id: 'account_id',
    category_id: 'category_id',
    type: 'type',
} as const;

/** Friendly sort keys used by the filter UI. */
export type SortKey = 'newest' | 'oldest' | 'highest' | 'lowest';

/** Maps a UI sort key to TanStack `SortingState`. */
export const SORT_STATE: Record<SortKey, SortingState> = {
    newest: [
        { id: 'transaction_date', desc: true },
        { id: 'id', desc: true },
    ],
    oldest: [
        { id: 'transaction_date', desc: false },
        { id: 'id', desc: false },
    ],
    highest: [{ id: 'amount', desc: true }],
    lowest: [{ id: 'amount', desc: false }],
};

/** Friendly labels per sort key — active tags and sheet options. */
export const SORT_LABELS: Record<SortKey, string> = {
    newest: 'Newest',
    oldest: 'Oldest',
    highest: 'Highest',
    lowest: 'Lowest',
};

/** Sort choices for the settings sheet, in display order. */
export const SORT_OPTIONS = (Object.entries(SORT_LABELS) as [SortKey, string][]).map(
    ([id, label]) => ({ id, label })
);

/** Reverse-derives the UI sort key from the table's current sorting state. */
export function toSortKey(sorting: SortingState): SortKey {
    const primary = sorting[0];

    if (!primary) return 'newest';
    if (primary.id === 'transaction_date') return primary.desc ? 'newest' : 'oldest';
    if (primary.id === 'amount') return primary.desc ? 'highest' : 'lowest';

    return 'newest';
}

/** Reads an array-valued column filter (empty array when the filter is off). */
export function getArrayFilter<T>(filters: ColumnFiltersState, columnId: string): T[] {
    const value = filters.find((filter) => filter.id === columnId)?.value;

    return Array.isArray(value) ? (value as T[]) : [];
}

/**
 * The period the list serves without an explicit `?period=` — the backend
 * (TransactionService) applies this window when none is requested.
 */
export const DEFAULT_PERIOD = DatePeriodPreset.Last6Months;

/* ── Day grouping ────────────────────────────────────── */

export interface DayGroup {
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

/** Groups chronologically-sorted rows into calendar-day buckets. */
export function groupTransactionsByDay(transactions: TransactionListData[]): DayGroup[] {
    const buckets = new SvelteMap<string, TransactionListData[]>();

    for (const transaction of transactions) {
        const day = transaction.transaction_date.slice(0, 10);
        const bucket = buckets.get(day);

        if (bucket) bucket.push(transaction);
        else buckets.set(day, [transaction]);
    }

    return [...buckets.entries()].map(([date, dayTransactions]) => ({
        date,
        transactions: dayTransactions,
        net: dayNet(dayTransactions),
    }));
}

/* ── Table wiring ────────────────────────────────────── */

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
        id: FILTER_COLUMNS.type,
        accessorFn: (row) => row.type,
        filterFn: 'arrayHas',
        enableGlobalFilter: false,
    },
];

/** Creates the filterable/sortable table instance behind a transaction list. */
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

/* ── Handler ─────────────────────────────────────────── */

interface HandlerOptions {
    /** Live view of the server prop (getter keeps reactivity). */
    transactions: () => TransactionListData[];
    /** Server paging state; omit on pages without server windows. */
    meta?: () => { total: number; has_more: boolean };
}

/** Screen controller created per page; components read from it and render. */
export interface TransactionListHandler {
    /** The filter/sort brain; components read atoms and set state through it. */
    readonly table: TransactionListTable;

    /** Table-filtered, flat rows in server order. */
    readonly filtered: TransactionListData[];
    /** Number of filtered rows on the client. */
    readonly count: number;
    /** Filtered rows grouped into calendar-day buckets. */
    readonly groups: DayGroup[];

    /** Debounced global filter — typing should not hammer the table on every keystroke. */
    search(value: string): void;

    /* ── Filter state (reads, live from the table atoms) ── */
    /** Account ids currently included by the account filter. */
    readonly accountFilter: number[];
    /** Category ids currently included by the category filter. */
    readonly categoryFilter: string[];
    /** Transaction types currently included by the type filter. */
    readonly typeFilter: App.Enums.TransactionType[];
    /** UI view of the current sort. */
    readonly sortKey: SortKey;
    /** Current global search text ('' when unset). */
    readonly searchValue: string;
    /** True when the server window differs from the default period. */
    readonly periodActive: boolean;

    /* ── Filter state (writes) ────────────────────── */
    /** Replaces the account filter; an empty array unsets it. */
    setAccountFilter(ids: number[]): void;
    /** Replaces the category filter; an empty array unsets it. */
    setCategoryFilter(ids: string[]): void;
    /** Replaces the type filter; an empty array unsets it. */
    setTypeFilter(types: App.Enums.TransactionType[]): void;
    /** Applies a UI sort key to the table. */
    setSort(key: SortKey): void;

    /** True when this handler backs server-side paging windows. */
    readonly paginated: boolean;
    /** Total matching rows in the period across all server pages. */
    readonly total: number | undefined;
    /** True when the server holds more rows than have been loaded so far. */
    readonly hasMore: boolean;
    /** True while the load-more request is in flight. */
    readonly loadingMore: boolean;
    loadMore(): void;

    /** The period the server is currently serving the list under. */
    readonly period: App.Enums.DatePeriodPreset;
    setPeriod(preset: App.Enums.DatePeriodPreset): void;

    /** Per-day open/closed overrides; untouched days stay undefined. */
    readonly dayOpen: SvelteMap<string, boolean>;

    /** True when any client filter, sort override, or period is active. */
    readonly hasActiveFilters: boolean;
    resetAllFilters(): void;
}

/**
 * Screen controller for the transaction list: the client filter/sort
 * brain, day grouping, and the two server-side windows — period (replace)
 * and load-more (append via Inertia::merge). It must be instantiated per
 * page; its containing components read from it and render.
 */
export function transactionListHandler({
    transactions,
    meta,
}: HandlerOptions): TransactionListHandler {
    /* ── Filter/sort brain ───────────────────────────── */
    const table = createTransactionListTable(transactions);

    const filtered = $derived(table.getFilteredRowModel().rows.map((row) => row.original));
    const count = $derived(filtered.length);

    const columnFilters = $derived(table.atoms.columnFilters.get());
    const accountFilter = $derived(
        getArrayFilter<number>(columnFilters, FILTER_COLUMNS.account_id)
    );
    const categoryFilter = $derived(
        getArrayFilter<string>(columnFilters, FILTER_COLUMNS.category_id)
    );
    const typeFilter = $derived(
        getArrayFilter<App.Enums.TransactionType>(columnFilters, FILTER_COLUMNS.type)
    );
    const sortKey = $derived(toSortKey(table.atoms.sorting.get()));
    const searchValue = $derived((table.atoms.globalFilter.get() as string | undefined) ?? '');

    // An empty array must unset the filter, otherwise the in-array filter fn
    // would reject every row.
    function setArrayFilter(columnId: string, ids: unknown[]): void {
        table.getColumn(columnId)?.setFilterValue(ids.length ? [...ids] : undefined);
    }

    function setAccountFilter(ids: number[]): void {
        setArrayFilter(FILTER_COLUMNS.account_id, ids);
    }

    function setCategoryFilter(ids: string[]): void {
        setArrayFilter(FILTER_COLUMNS.category_id, ids);
    }

    function setTypeFilter(types: App.Enums.TransactionType[]): void {
        setArrayFilter(FILTER_COLUMNS.type, types);
    }

    function setSort(key: SortKey): void {
        table.setSorting(SORT_STATE[key]);
    }

    const SEARCH_DEBOUNCE_MS = 300;
    let searchTimer: ReturnType<typeof setTimeout> | undefined;

    /** Debounced global filter — typing should not hammer the table on every keystroke. */
    function search(value: string) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(
            (): void => table.setGlobalFilter(value || undefined),
            SEARCH_DEBOUNCE_MS
        );
    }

    /* ── Period (server window, replaces the dataset) ─── */
    let period = $state<App.Enums.DatePeriodPreset>(DEFAULT_PERIOD);

    /**
     * Period has no cursor: the server answers page one of the preset and,
     * without a merge label (see TransactionController), the prop REPLACES
     * — cursor resets naturally with the new dataset, while filters/sort
     * survive because reload keeps state.
     */
    function setPeriod(preset: App.Enums.DatePeriodPreset) {
        if (preset === period) return;
        period = preset;
        router.reload({
            // Explicit empty cursor forces page one server-side
            // (fromWire('') → null), even if a stale cursor param hid in
            // the URL query.
            data: { period: preset, cursor: '' },
            only: ['transactions', 'transactions_meta', 'summary'],
            preserveUrl: true,
        });
    }

    /* ── Load-more (server window, appends pages) ─────── */
    let loadingMore = $state(false);

    /** True when the server window differs from the default period. */
    const periodActive = $derived(period !== DEFAULT_PERIOD);

    function loadMore() {
        if (loadingMore || transactions().length === 0) return;
        /**
         * Cursor = the (transaction_date, id) of the last loaded row. The
         * server appends the next page onto the prop itself (Inertia::merge),
         * so the last element after every fetch is always the loading point.
         * `preserveUrl` keeps cursor/period out of pushState history — both
         * always travel explicitly in `data`.
         */
        const { transaction_date, id } = transactions()[transactions().length - 1];
        loadingMore = true;
        router.reload({
            data: { cursor: `${transaction_date}|${id}`, period },
            only: ['transactions', 'transactions_meta'],
            preserveUrl: true,
            onFinish: () => (loadingMore = false),
        });
    }

    /* ── Day groups ───────────────────────────────────── */
    const groups = $derived.by<DayGroup[]>(() => groupTransactionsByDay(filtered));

    // Per-day collapse choices, aggregated in one map across instances of
    // the same list; untouched days fall back to the renderer default.
    const dayOpen = new SvelteMap<string, boolean>();

    /* ── Active filters ───────────────────────────────── */
    const hasActiveFilters = $derived(
        table.atoms.columnFilters.get().length > 0 ||
            Boolean(table.atoms.globalFilter.get()) ||
            toSortKey(table.atoms.sorting.get()) !== 'newest' ||
            period !== DEFAULT_PERIOD
    );

    function resetAllFilters() {
        table.resetGlobalFilter();
        table.resetColumnFilters();
        table.setSorting(SORT_STATE.newest);
        setPeriod(DEFAULT_PERIOD);
    }

    return {
        /** The filter/sort brain; components read atoms and set state through it. */
        table,

        get filtered() {
            return filtered;
        },
        get count() {
            return count;
        },
        get groups() {
            return groups;
        },
        search,

        /* ── Filter state (reads) ─────────────────────── */
        get accountFilter() {
            return accountFilter;
        },
        get categoryFilter() {
            return categoryFilter;
        },
        get typeFilter() {
            return typeFilter;
        },
        get sortKey() {
            return sortKey;
        },
        get searchValue() {
            return searchValue;
        },
        /** True when the server window differs from the default period. */
        get periodActive() {
            return periodActive;
        },

        /* ── Filter state (writes) ────────────────────── */
        setAccountFilter,
        setCategoryFilter,
        setTypeFilter,
        setSort,

        /** True when this handler backs server-side paging windows. */
        get paginated() {
            return meta !== undefined;
        },
        get total() {
            return meta?.().total;
        },
        get hasMore() {
            return meta?.().has_more ?? false;
        },
        get loadingMore() {
            return loadingMore;
        },
        loadMore,

        get period() {
            return period;
        },
        setPeriod,

        /** Per-day open/closed overrides; untouched days stay undefined. */
        get dayOpen() {
            return dayOpen;
        },
        get hasActiveFilters() {
            return hasActiveFilters;
        },
        resetAllFilters,
    };
}
