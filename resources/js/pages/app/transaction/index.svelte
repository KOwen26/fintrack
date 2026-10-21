<script lang="ts">
    import type { TransactionListData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { transactionListHandler } from '@services/transaction-list.svelte';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TransactionListFilter from '@components/module/transaction/transaction-list-filter.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import TransactionSummaryCard from '@components/module/transaction/transaction-summary-card.svelte';
    import Skeleton from '@components/ui/skeleton.svelte';

    let {
        transactions,
        summary,
        accounts,
        categories,
        period,
        transactions_meta: meta,
    }: {
        /** Undefined while the deferred window is still in flight. */
        transactions: TransactionListData[] | undefined;
        summary: { income: number; expense: number; net: number };
        accounts: {
            id: number;
            name: string;
            decorations?: { icon?: string | null; color?: string | null } | null;
        }[];
        categories: {
            id: string;
            name: string;
            decorations?: { icon?: string | null; color?: string | null } | null;
        }[];
        /** The window the server is actually serving — seeds the list state. */
        period: App.Enums.DatePeriodPreset;
        /** Absent in full-window mode — paging meta is paged-mode only. */
        transactions_meta?:
            { total: number | null; has_more: boolean; next_cursor: string | null } | undefined;
    } = $props();

    /* ── List controller: client engine over the full window ── */
    const list = transactionListHandler({
        transactions: () => transactions,
        meta: () => meta,
        period: () => period,
        engine: 'client',
    });
</script>

<MobilePageLayout contentClass="[timeline-scope:--filter-stuck]" variant="full">
    {#snippet hero()}
        <TransactionSummaryCard {summary} />
    {/snippet}

    <!-- ── SEARCH + FILTERS (sticky in content) ───────────── -->
    <!-- Non-sticky sentinel: Chromium's view() timelines don't advance for position:sticky subjects, so this 1px marker (invisible to layout) crosses the scrollport top exactly when the bar sticks. -->
    <div class="filter-sentinel" aria-hidden="true"></div>
    <div
        class="sticky-filter sticky top-0 z-20 -mx-5 border-b border-transparent bg-background px-5 py-2.5">
        <TransactionListFilter {accounts} {categories} {list} />
    </div>

    {#if transactions === undefined}
        <!-- Deferred window in flight — skeleton, not the empty state. -->
        <div class="flex flex-col gap-3">
            {#each Array(6) as _, i (i)}
                <Skeleton class="h-20 w-full" />
            {/each}
        </div>
    {:else if list.filtered.length === 0}
        {#if list.hasActiveFilters}
            <EmptyItemPlaceholder
                ctaLabel="Reset all filters"
                ctaOnclick={list.resetAllFilters}
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
        <TransactionList {list} variant="flat" />
    {/if}

    <div class="mb-5"></div>
</MobilePageLayout>

<style>
    /* Scroll-driven "stuck" styling: the view() timeline tracks this element through the scrollport; `contain 99% -> 100%` ends exactly at the stick moment, and while stuck the timeline freezes there, holding the styles. Browsers without scroll-driven animations simply keep the bar flat. */
    .filter-sentinel {
        height: 1px;
        margin-bottom: -1px;
        view-timeline: --filter-stuck;
    }

    .sticky-filter {
        animation: sticky-filter-stuck linear both;
        animation-timeline: --filter-stuck;
        animation-range-start: exit 0%;
        animation-range-end: exit 100%;
    }

    @keyframes sticky-filter-stuck {
        to {
            border-color: var(--color-border);
            box-shadow:
                0 1px 3px 0 rgb(0 0 0 / 0.1),
                0 1px 2px -1px rgb(0 0 0 / 0.1);
        }
    }
</style>
