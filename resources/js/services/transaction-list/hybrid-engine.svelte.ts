import type { EngineDeps, TransactionListEngineImpl } from './contract.svelte';
import type { App } from '@wayfinder/types';

import { createCursorPagination, createTableBrain } from './contract.svelte';

import { router } from '@inertiajs/svelte';

/**
 * Hybrid engine: the server ships keyset pages (append via merge) while the
 * browser filters/sorts the loaded slice. Accuracy is bounded by what has
 * been loaded; transport stays light.
 */
export function createHybridEngine(deps: EngineDeps): TransactionListEngineImpl {
    const brain = createTableBrain(deps.transactions);
    const pagination = createCursorPagination(deps);

    return {
        get filtered() {
            return brain.filtered;
        },
        get rows() {
            return brain.filtered;
        },
        get count() {
            return brain.filtered.length;
        },
        // Paged mode: the server's meta is the matching-row total.
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
            return false; // filtering is synchronous
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

        search: brain.search,
        clearSearch: brain.clearSearch,
        setAccountFilter: brain.setAccountFilter,
        setCategoryFilter: brain.setCategoryFilter,
        setTypeFilter: brain.setTypeFilter,
        setSort: brain.setSort,
        resetLocalFilters: brain.resetLocalFilters,

        applyPeriod(): void {
            // Explicit empty cursor forces page one server-side
            // (fromRequest('') → null), even if a stale cursor param hid in
            // the URL query. A null period omits the param — the backend
            // serves its default and the prop echoes it back.
            router.reload({
                data: { period: deps.period() ?? undefined, cursor: '' },
                only: ['transactions', 'transactions_meta', 'summary', 'period'],
                preserveUrl: true,
            });
        },
    };
}
