<script lang="ts">
    import type { TransactionListHandler } from '@services/transaction-list.svelte';
    import type { TransactionListData } from '@type/generated';
    import type { RestProps } from '@type/index';

    import TransactionListItem from './transaction-list-item.svelte';

    import { Collapsible } from 'bits-ui';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import { cn } from '@utilities/shadcn';
    import { groupTransactionsByDay } from '@services/transaction-list.svelte';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import Button from '@components/ui/button.svelte';
    import Separator from '@components/ui/separator.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        /** Service-backed handler (filter brain + server windows) — takes precedence over `transactions`. */
        list?: TransactionListHandler;
        /** Plain rows for static embeds (dashboard, account detail). */
        transactions?: TransactionListData[];
        class?: string;
        hideTotal?: boolean;
        hideControl?: boolean;
        /** Load-more action for static mode; the service mode uses `list.loadMore`. */
        loadMoreAction?: () => void;
        /** True when the server holds more rows than have been loaded so far (static mode). */
        hasMore?: boolean;
        /** Total matching rows across all server pages (static mode). */
        total?: number;
        /** True while the load-more request is in flight (static mode). */
        loadingMore?: boolean;
        variant?: 'collapsible' | 'flat';
    }

    let {
        list,
        transactions,
        class: _class,
        hideTotal = false,
        hideControl = false,
        loadMoreAction,
        hasMore = false,
        total,
        loadingMore = false,
        variant = 'collapsible',
    }: Props = $props();

    /* ── Unified render sources ──────────────────────────── */

    const rows = $derived(list?.filtered ?? transactions ?? []);
    const groups = $derived(list ? list.groups : groupTransactionsByDay(transactions ?? []));
    const displayedCount = $derived(list ? list.count : rows.length);
    const pagerHasMore = $derived(list ? list.hasMore : hasMore);
    const pagerAction = $derived(list ? list.loadMore : loadMoreAction);
    const pagerBusy = $derived(list ? list.loadingMore : loadingMore);
    const pagerTotal = $derived(list ? list.total : total);

    /* ── Day collapse state ──────────────────────────────── */

    // Explicit open/closed choices per day; untouched days fall back to the
    // default: the first group (today) open, all others collapsed.
    const dayOpenOverrides = new SvelteMap<string, boolean>();
</script>

<div class={cn('flex flex-col gap-3', _class)}>
    {#if !hideTotal}
        <!-- Count header -->
        <p class="mx-0.5 mt-1 mb-0 text-sm text-foreground/70">
            {displayedCount}
            transactions
        </p>
    {/if}

    <div class="flex flex-col">
        {#each groups as group, i (group.date)}
            {#if variant === 'collapsible'}
                <Collapsible.Root
                    onOpenChange={(open) => dayOpenOverrides.set(group.date, open)}
                    open={dayOpenOverrides.get(group.date) ??
                        i <= Math.min(Math.ceil(groups.length / 3), 7)}>
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

            <Separator class="mt-2 mb-4 last:hidden" />
        {/each}
    </div>

    {#if !hideControl && pagerHasMore && pagerAction}
        <Button
            class="mt-2 w-full"
            color="light"
            disabled={pagerBusy}
            onclick={pagerAction}
            variant="soft">
            {#if pagerBusy}
                <i class="iconify size-4 animate-spin solar--refresh-bold-duotone"></i>
            {:else}
                <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
            {/if}

            {#if pagerTotal !== undefined}
                {displayedCount} of {pagerTotal} transactions
            {:else}
                Load more ({displayedCount})
            {/if}
        </Button>
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
