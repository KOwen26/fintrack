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

/**
 * The internal seam between the handler facade and its engines. Public
 * consumers only ever see `TransactionListHandler` (the facade); this shape
 * is what each per-engine file implements.
 */
export interface TransactionListEngineImpl {
    /** Post-filter rows (client engine: the full filtered window). */
    readonly filtered: TransactionListData[];
    /** Rows the current pagination step exposes (client: revealed slice). */
    readonly rows: TransactionListData[];
    /** Number of exposed rows. */
    readonly count: number;
    /**
     * Authoritative matching-row total under the current query. The client
     * engine knows its whole window, so the answer is local
     * (`filtered.length`); cursor engines defer to the server's meta.
     */
    readonly total: number | undefined;
    readonly hasMore: boolean;
    readonly loadingMore: boolean;
    readonly applying: boolean;
    loadMore(): void;

    /* ── Filter state (reads) ─────────────────────── */
    readonly accountFilter: number[];
    readonly categoryFilter: string[];
    readonly typeFilter: App.Enums.TransactionType[];
    readonly sortKey: SortKey;
    readonly searchValue: string;
    /** True when engine-local filters/sort are active — period excluded. */
    readonly hasLocalFilters: boolean;

    /* ── Filter state (writes) ────────────────────── */
    search(value: string): void;
    clearSearch(): void;
    setAccountFilter(ids: number[]): void;
    setCategoryFilter(ids: string[]): void;
    setTypeFilter(types: App.Enums.TransactionType[]): void;
    setSort(key: SortKey): void;
    resetLocalFilters(): void;

    /**
     * Applies the period choice the facade has already written to its state
     * (null = no choice) — the engine routes the request with its own serving
     * semantics (client: full window; cursor engines: page one, paged;
     * server: coalesced flush). A null period omits the param so the
     * backend serves its default.
     */
    applyPeriod(): void;
}

/** Reactive view the facade shares with every engine. */
export interface EngineDeps {
    /** Normalized server rows — never undefined. */
    transactions: () => TransactionListData[];
    metaState: () => {
        total: number | null;
        has_more: boolean;
        next_cursor: string | null;
    };
    /** Applied period choice; null = follow the backend default. */
    period: () => App.Enums.DatePeriodPreset | null;
}

/* ── Shared vocabulary (client + hybrid table brain) ─── */

/** Column ids on the transaction list table that the filter UI drives. */
export const FILTER_COLUMNS = {
    account_id: 'account_id',
    category_id: 'category_id',
    type: 'type',
} as const;

/** UI sort key — exported publicly through the facade. */
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

/** Reverse-derives the UI sort key from the table's current sorting state. */
function toSortKey(sorting: SortingState): SortKey {
    const primary = sorting[0];

    if (!primary) return 'newest';
    if (primary.id === 'transaction_date') return primary.desc ? 'newest' : 'oldest';
    if (primary.id === 'amount') return primary.desc ? 'highest' : 'lowest';

    return 'newest';
}

/** Reads an array-valued column filter (empty array when the filter is off). */
function getArrayFilter<T>(filters: ColumnFiltersState, columnId: string): T[] {
    const value = filters.find((filter) => filter.id === columnId)?.value;

    return Array.isArray(value) ? (value as T[]) : [];
}

/* ── TanStack brain (client + hybrid) ────────────────── */

const transactionListFeatures = tableFeatures({
    rowSortingFeature,
    columnFilteringFeature,
    globalFilteringFeature,

    sortedRowModel: createSortedRowModel(),
    filteredRowModel: createFilteredRowModel(),

    filterFns: {
        arrayHas: filterFn_arrHas,
    },
});

type TransactionListFeatures = typeof transactionListFeatures;

type TransactionListTable = SvelteTable<TransactionListFeatures, TransactionListData>;

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
function createTransactionListTable(
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

/** The filter/sort state container and read/write surface every engine shares. */
export interface TableBrain {
    /** Post-filter rows from the local row model (never read by the server engine). */
    readonly filtered: TransactionListData[];
    readonly accountFilter: number[];
    readonly categoryFilter: string[];
    readonly typeFilter: App.Enums.TransactionType[];
    readonly sortKey: SortKey;
    readonly searchValue: string;
    /** True when any local filter, search, or sort override is active. */
    readonly hasLocalFilters: boolean;

    /** Debounced search — typing should not hammer its target on every keystroke. */
    search(value: string): void;
    /** Immediate search write — engines with their own debounce call this. */
    setSearch(value: string): void;
    /** Cancels a pending debounced search and clears the applied one. */
    clearSearch(): void;
    setAccountFilter(ids: number[]): void;
    setCategoryFilter(ids: string[]): void;
    setTypeFilter(types: App.Enums.TransactionType[]): void;
    setSort(key: SortKey): void;
    resetLocalFilters(): void;
}

/**
 * The client-side filter/sort brain shared by every engine: a TanStack table
 * used as the canonical filter/sort STATE container, plus the read/write
 * surface over its atoms. Client/hybrid engines also consume its row model;
 * the server engine reads only the state (its row model is never consulted —
 * the server executes the query).
 */
export function createTableBrain(transactions: () => TransactionListData[]): TableBrain {
    const table = createTransactionListTable(transactions);

    const filtered = $derived(table.getFilteredRowModel().rows.map((row) => row.original));

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
    const hasLocalFilters = $derived(
        columnFilters.length > 0 || Boolean(table.atoms.globalFilter.get()) || sortKey !== 'newest'
    );

    // An empty array must unset the filter, otherwise the in-array filter fn
    // would reject every row.
    function setArrayFilter(columnId: string, ids: unknown[]): void {
        table.getColumn(columnId)?.setFilterValue(ids.length ? [...ids] : undefined);
    }

    const SEARCH_DEBOUNCE_MS = 300;
    let searchTimer: ReturnType<typeof setTimeout> | undefined;

    /** Immediate search write — engines with their own debounce call this. */
    function setSearch(value: string) {
        table.setGlobalFilter(value || undefined);
    }

    /** Debounced search — typing should not hammer the table on every keystroke. */
    function search(value: string) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout((): void => setSearch(value), SEARCH_DEBOUNCE_MS);
    }

    /** Cancels a pending debounced search and clears the applied one. */
    function clearSearch() {
        clearTimeout(searchTimer);
        table.setGlobalFilter(undefined);
    }

    return {
        get filtered() {
            return filtered;
        },
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
        get hasLocalFilters() {
            return hasLocalFilters;
        },
        search,
        setSearch,
        clearSearch,
        setAccountFilter: (ids: number[]): void => setArrayFilter(FILTER_COLUMNS.account_id, ids),
        setCategoryFilter: (ids: string[]): void => setArrayFilter(FILTER_COLUMNS.category_id, ids),
        setTypeFilter: (types: App.Enums.TransactionType[]): void =>
            setArrayFilter(FILTER_COLUMNS.type, types),
        setSort: (key: SortKey): void => table.setSorting(SORT_STATE[key]),
        resetLocalFilters: (): void => {
            table.resetGlobalFilter();
            table.resetColumnFilters();
            table.setSorting(SORT_STATE.newest);
        },
    };
}

/* ── Cursor pagination (hybrid + server) ─────────────── */

/** Append-style pagination driven by the server-emitted `next_cursor`. */
export interface CursorPagination {
    readonly hasMore: boolean;
    readonly loadingMore: boolean;
    loadMore(): void;
}

/**
 * Append-style pagination driven by the server-emitted `next_cursor` — the
 * client never derives cursor internals.
 */
export function createCursorPagination(deps: EngineDeps): CursorPagination {
    let loadingMore = $state(false);

    return {
        get hasMore() {
            return deps.metaState().has_more;
        },
        get loadingMore() {
            return loadingMore;
        },
        loadMore() {
            const cursor = deps.metaState().next_cursor;

            if (loadingMore || cursor === null) return;

            loadingMore = true;
            router.reload({
                data: { cursor, period: deps.period() ?? undefined },
                only: ['transactions', 'transactions_meta'],
                preserveUrl: true,
                onFinish: () => (loadingMore = false),
            });
        },
    };
}
