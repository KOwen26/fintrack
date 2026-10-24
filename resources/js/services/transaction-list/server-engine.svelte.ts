import type { EngineDeps, SortKey, TransactionListEngineImpl } from './contract.svelte';
import type { App } from '@wayfinder/types';

import {
    createCursorPagination,
    createTableBrain,
    FILTER_COLUMNS,
    SORT_STATE,
} from './contract.svelte';

import { router } from '@inertiajs/svelte';

const SEARCH_DEBOUNCE_MS = 300;

/**
 * Server engine: the browser only holds one page. The TanStack table state
 * (`TableBrain`) is the canonical filter/sort container — the same vocabulary
 * the client engines use — but its row model is never read: every state write
 * maps onto request parameters and the server executes the query. All writes
 * coalesce into a single replace reload (the drawer's multi-setter Apply
 * lands as one request); that reload carries no cursor, so the server
 * answers page one and the prop replaces (no merge label).
 */
export function createServerEngine(deps: EngineDeps): TransactionListEngineImpl {
    const brain = createTableBrain(deps.transactions);
    const pagination = createCursorPagination(deps);

    let applying = $state(false);
    let flushTimer: ReturnType<typeof setTimeout> | undefined;

    /**
     * One coalesced replace reload carrying the table's state structure
     * itself: the global filter, the active column filters, and the sort
     * descriptor — the exact shapes TanStack holds. TransactionIndexRequest
     * validates the structure; TransactionListFilters::fromRequest maps it.
     * Unset filters are omitted, mirroring table semantics (an entry with an
     * empty value never exists).
     */
    function flush(): void {
        clearTimeout(flushTimer);
        applying = true;

        const filters = [
            ...(brain.accountFilter.length
                ? [{ id: FILTER_COLUMNS.account_id, value: brain.accountFilter }]
                : []),
            ...(brain.categoryFilter.length
                ? [{ id: FILTER_COLUMNS.category_id, value: brain.categoryFilter }]
                : []),
            ...(brain.typeFilter.length
                ? [{ id: FILTER_COLUMNS.type, value: brain.typeFilter }]
                : []),
        ];

        router.reload({
            data: {
                global_filter: brain.searchValue,
                filters,
                sorting: SORT_STATE[brain.sortKey].map(({ id, desc }) => ({ id, desc })),
                period: deps.period() ?? undefined,
                cursor: '',
            },
            only: ['transactions', 'transactions_meta', 'summary', 'period'],
            preserveUrl: true,
            onFinish: () => (applying = false),
        });
    }

    /** Coalesces synchronous setter bursts into a single flush. */
    function scheduleFlush(): void {
        clearTimeout(flushTimer);
        flushTimer = setTimeout(flush, 0);
    }

    return {
        // The server pre-filters — the prop IS the query result, and the
        // brain's local row model is deliberately not consulted.
        get filtered() {
            return deps.transactions();
        },
        get rows() {
            return deps.transactions();
        },
        get count() {
            // Exposed rows: in server mode that is exactly the one page we
            // hold — the matching total never belongs to `count`.
            return deps.transactions().length;
        },
        get total() {
            return deps.metaState().total ?? undefined;
        },
        get hasMore() {
            return pagination.hasMore;
        },
        get loadingMore() {
            return pagination.loadingMore;
        },
        get applying() {
            return applying;
        },
        loadMore: pagination.loadMore,

        get accountFilter() {
            return brain.accountFilter;
        },
        get categoryFilter() {
            return brain.categoryFilter;
        },
        get typeFilter() {
            return brain.typeFilter;
        },
        get sortKey() {
            return brain.sortKey;
        },
        get searchValue() {
            return brain.searchValue;
        },
        get hasLocalFilters() {
            return brain.hasLocalFilters;
        },

        search(value: string): void {
            brain.setSearch(value);
            clearTimeout(flushTimer);
            flushTimer = setTimeout(flush, SEARCH_DEBOUNCE_MS);
        },
        clearSearch(): void {
            brain.clearSearch();
            flush();
        },
        setAccountFilter(ids: number[]): void {
            brain.setAccountFilter(ids);
            scheduleFlush();
        },
        setCategoryFilter(ids: string[]): void {
            brain.setCategoryFilter(ids);
            scheduleFlush();
        },
        setTypeFilter(types: App.Enums.TransactionType[]): void {
            brain.setTypeFilter(types);
            scheduleFlush();
        },
        setSort(key: SortKey): void {
            brain.setSort(key);
            scheduleFlush();
        },
        resetLocalFilters: brain.resetLocalFilters,

        applyPeriod(): void {
            // The facade already wrote the period; the flush reads it (null
            // omits the param). No equality guard — an Apply with an
            // unchanged period must still carry the other pending changes.
            scheduleFlush();
        },
    };
}
