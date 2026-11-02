import type { EngineDeps, TransactionListEngineImpl } from './contract.svelte';

import { createTableBrain } from './contract.svelte';

import { router } from '@inertiajs/svelte';

/** Fraction of the window revealed per step. */
const REVEAL_FRACTION = 5;

/**
 * Client engine: the server ships the whole period window (its serving-mode
 * constant, deferred) and everything — search, filter, sort, reveal
 * pagination — runs in the browser over it.
 */
export function createClientEngine(deps: EngineDeps): TransactionListEngineImpl {
    const brain = createTableBrain(deps.transactions);

    const filtered = $derived(brain.filtered);

    /* ── Reveal pagination ────────────────────────────── */
    const revealChunk = $derived(
        Math.max(1, Math.ceil(deps.transactions().length / REVEAL_FRACTION))
    );
    let revealCount = $derived(Math.ceil(deps.transactions().length / REVEAL_FRACTION));

    // A fresh window (a period change replaces the prop) restarts the reveal.
    // $effect(() => {
    //     revealCount = revealChunk;
    // });

    const rows = $derived(filtered.slice(0, revealCount));

    return {
        get filtered() {
            return filtered;
        },
        get rows() {
            return rows;
        },
        get count() {
            return rows.length;
        },
        // The whole window is in the browser — the filtered length IS the
        // total; no server meta needed.
        get total() {
            return filtered.length;
        },
        get hasMore() {
            return revealCount < filtered.length;
        },
        get loadingMore() {
            return false; // reveal is synchronous
        },
        get applying() {
            return false; // filtering is synchronous
        },
        loadMore() {
            revealCount = Math.min(revealCount + revealChunk, filtered.length);
        },

        get accountFilter() {
            return brain.accountFilter;
        },
        get categoryFilter() {
            return brain.categoryFilter;
        },
        get typeFilter() {
            return brain.typeFilter;
        },
        get tagFilter() {
            return brain.tagFilter;
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
        setTagFilter: brain.setTagFilter,
        setSort: brain.setSort,
        resetLocalFilters: brain.resetLocalFilters,

        applyPeriod(): void {
            router.reload({
                data: { period: deps.period() },
                only: ['transactions', 'summary', 'period'],
                preserveUrl: true,
            });
        },
    };
}
