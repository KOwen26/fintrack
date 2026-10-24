import type { SortKey } from './transaction-list/contract.svelte';
import type { TransactionListData } from '@type/generated';
import type { App } from '@wayfinder/types';

import { createClientEngine } from './transaction-list/client-engine.svelte';
import { createHybridEngine } from './transaction-list/hybrid-engine.svelte';
import { createServerEngine } from './transaction-list/server-engine.svelte';

import { page } from '@inertiajs/svelte';
import { SvelteMap, SvelteURLSearchParams } from 'svelte/reactivity';

export type { SortKey } from './transaction-list/contract.svelte';

/* ── Public vocabulary ───────────────────────────────── */

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

/* ── Handler ─────────────────────────────────────────── */

/**
 * Where search/filter/sort execute. Period and the server contract are the
 * same in every engine; only query locality and pagination mechanics differ.
 * The serving mode is a server-owned constant (TransactionController::
 * SERVING_WINDOW) that pairs with the engine — never request-driven.
 *
 * - `client`: the server ships the whole period window; search/filter/sort +
 *   reveal pagination run in the browser.
 * - `hybrid`: the server ships keyset pages; the browser filters/sorts the
 *   loaded slice.
 * - `server`: the browser only holds a page; every filter, search, sort and
 *   the pagination cursor are server parameters (spec:
 *   docs/specs/transaction-list-server-side-search-sort.md).
 */
export type TransactionListEngine = 'client' | 'hybrid' | 'server';

interface HandlerOptions {
    /**
     * Live view of the server prop (getter keeps reactivity). Undefined
     * while an `Inertia::defer` prop is still in flight.
     */
    transactions?: () => TransactionListData[] | undefined;
    /** Server paging state; omit on pages without server windows. */
    meta?: () =>
        | {
              total: number | null;
              has_more: boolean;
              next_cursor: string | null;
          }
        | undefined;
    /**
     * The served `period` prop — the window the backend is actually
     * providing. Always present on the transaction page; drives the
     * effective period whenever no explicit choice is applied.
     */
    period: () => App.Enums.DatePeriodPreset;
    /** Defaults to `client` — the deployed pairing for the full-window serving mode. */
    engine?: TransactionListEngine;
}

/** Screen controller created per page; components read from it and render. */
export interface TransactionListHandler {
    /** Table-filtered rows (client engine: the full filtered window). */
    readonly filtered: TransactionListData[];
    /** Rows exposed by the current pagination step. */
    readonly rows: TransactionListData[];
    /** Number of exposed rows on the client. */
    readonly count: number;
    /** Exposed rows grouped into calendar-day buckets. */
    readonly groups: DayGroup[];

    /** Debounced search — typing should not hammer its target on every keystroke. */
    search(value: string): void;
    /** Cancels a pending debounced search and clears the applied one. */
    clearSearch(): void;
    /**
     * True while a replace reload is in flight. The client engines filter
     * synchronously and report false; the server engine toggles it so
     * callers may surface in-flight state.
     */
    readonly applying: boolean;

    /* ── Filter state (reads) ─────────────────────── */
    /** Account ids currently included by the account filter. */
    readonly accountFilter: number[];
    /** Category ids currently included by the category filter. */
    readonly categoryFilter: string[];
    /** Transaction types currently included by the type filter. */
    readonly typeFilter: App.Enums.TransactionType[];
    /** UI view of the current sort. */
    readonly sortKey: SortKey;
    /** Current search text ('' when unset). */
    readonly searchValue: string;
    /** True while an explicit period choice is applied (clearable to the backend default). */
    readonly periodActive: boolean;

    /* ── Filter state (writes) ────────────────────── */
    /** Replaces the account filter; an empty array unsets it. */
    setAccountFilter(ids: number[]): void;
    /** Replaces the category filter; an empty array unsets it. */
    setCategoryFilter(ids: string[]): void;
    /** Replaces the type filter; an empty array unsets it. */
    setTypeFilter(types: App.Enums.TransactionType[]): void;
    /** Applies a UI sort key. */
    setSort(key: SortKey): void;

    /** True when this handler backs server-side paging windows. */
    readonly paginated: boolean;
    /** Total matching rows across all server pages. */
    readonly total: number | undefined;
    /** True when more rows exist past the current pagination step. */
    readonly hasMore: boolean;
    /** True while the load-more request is in flight. */
    readonly loadingMore: boolean;
    loadMore(): void;

    /** The period in effect: an applied choice, else the served prop. */
    readonly period: App.Enums.DatePeriodPreset;
    /** Applies an explicit period choice (one reload). */
    setPeriod(preset: App.Enums.DatePeriodPreset): void;
    /** Drops the period choice — the reload omits the param and the backend serves its default. */
    clearPeriod(): void;

    /** Per-day open/closed overrides; untouched days stay undefined. */
    readonly dayOpen: SvelteMap<string, boolean>;

    /** True when any filter, sort override, or period is active. */
    readonly hasActiveFilters: boolean;
    resetAllFilters(): void;
}

/**
 * Screen controller for the transaction list. Composes the shared state
 * (server props, period, day grouping) with one of three engine
 * implementations — see `TransactionListEngine`. It must be instantiated per
 * page; its containing components read from it and render.
 */
export function transactionListHandler({
    transactions: source = (): TransactionListData[] => [],
    meta,
    period,
    engine = 'client',
}: HandlerOptions): TransactionListHandler {
    // Deferred props read as undefined until their request resolves — the whole engine works against a plain array.
    const transactions = (): TransactionListData[] => source() ?? [];

    /**
     * Server paging state normalized to an object — the getter itself may
     * return undefined, and `total` may be null while a window is deferred.
     */
    const metaState = $derived(meta?.() ?? { total: null, has_more: false, next_cursor: null });

    /* ── Shared state ─────────────────────────────────── */
    // The backend owns the default: `null` means "no explicit choice" and
    // the served prop drives the effective period. A choice stays optimistic
    // until its response lands; clearing drops the param so the backend
    // serves — and echoes — its own default again.
    let periodState = $state<App.Enums.DatePeriodPreset | null>(
        // A deep link that requested ?period= is an applied choice; a plain
        // visit is "follow the backend".
        new SvelteURLSearchParams(page.url.split('?')[1] ?? '').has('period') ? period() : null
    );

    const periodActive = $derived(periodState !== null);

    const deps = {
        transactions,
        metaState: (): typeof metaState => metaState,
        period: (): App.Enums.DatePeriodPreset | null => periodState,
    };

    const handler =
        engine === 'client'
            ? createClientEngine(deps)
            : engine === 'server'
              ? createServerEngine(deps)
              : createHybridEngine(deps);

    /* ── Shared derivations ───────────────────────────── */
    const groups = $derived(groupTransactionsByDay(handler.rows));

    // Per-day collapse choices, aggregated in one map across instances of
    // the same list; untouched days fall back to the renderer default.
    const dayOpen = new SvelteMap<string, boolean>();

    const hasActiveFilters = $derived(handler.hasLocalFilters || periodActive);

    function setPeriod(preset: App.Enums.DatePeriodPreset): void {
        if (preset === periodState) return;
        periodState = preset;
        handler.applyPeriod();
    }

    function clearPeriod(): void {
        if (periodState === null) return;
        periodState = null;
        handler.applyPeriod();
    }

    return {
        get filtered() {
            return handler.filtered;
        },
        get rows() {
            return handler.rows;
        },
        get count() {
            return handler.count;
        },
        get groups() {
            return groups;
        },
        search: handler.search,
        clearSearch: handler.clearSearch,
        get applying() {
            return handler.applying;
        },

        /* ── Filter state (reads) ─────────────────────── */
        get accountFilter() {
            return handler.accountFilter;
        },
        get categoryFilter() {
            return handler.categoryFilter;
        },
        get typeFilter() {
            return handler.typeFilter;
        },
        get sortKey() {
            return handler.sortKey;
        },
        get searchValue() {
            return handler.searchValue;
        },
        get periodActive() {
            return periodActive;
        },

        /* ── Filter state (writes) ────────────────────── */
        setAccountFilter: handler.setAccountFilter,
        setCategoryFilter: handler.setCategoryFilter,
        setTypeFilter: handler.setTypeFilter,
        setSort: handler.setSort,

        get paginated() {
            return meta !== undefined;
        },
        get total() {
            // Engine-owned: client answers locally, cursor engines defer to
            // the server meta.
            return handler.total;
        },
        get hasMore() {
            return handler.hasMore;
        },
        get loadingMore() {
            return handler.loadingMore;
        },
        loadMore: handler.loadMore,

        get period() {
            return periodState ?? period();
        },
        setPeriod,
        clearPeriod,

        get dayOpen() {
            return dayOpen;
        },
        get hasActiveFilters() {
            return hasActiveFilters;
        },
        resetAllFilters(): void {
            handler.resetLocalFilters();
            clearPeriod();
        },
    };
}
