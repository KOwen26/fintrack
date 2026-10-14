<script lang="ts">
    import type { CategorySpendingReportData, ParentSpendingItemData } from '@type/generated';

    import { getDecorationColor } from '@data/decoration-colors';

    import Formatter from '@utilities/formatter';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import Button from '@components/ui/button.svelte';
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    let {
        categorySpending,
        periodLabel,
        emptyMessage = 'No spending data for this period',
        variant = 'donut',
    }: {
        categorySpending: CategorySpendingReportData;
        periodLabel?: string;
        emptyMessage?: string;
        variant?: 'donut' | 'bar';
    } = $props();

    const categories = $derived(categorySpending.categories);
    const periodTotal = $derived(categorySpending.period_total);

    // View state: 'parent' shows grouped parents, 'children' shows one parent's breakdown
    let view: 'parent' | 'children' = $state('parent');
    let selectedGroup: ParentSpendingItemData | null = $state(null);
    let donutSelectedKey = $state<string | null>(null);

    // --- Derived data ---

    const currentSlices = $derived(
        view === 'parent'
            ? categories.map((g) => ({
                  name: g.name,
                  value: g.total,
                  color: getDecorationColor(g.color)?.oklch ?? g.color,
              }))
            : selectedGroup!.children.map((c) => ({
                  name: c.name,
                  value: c.total,
                  color: getDecorationColor(c.color)?.oklch ?? c.color,
              }))
    );

    const currentCenterTotal = $derived(
        view === 'parent' ? periodTotal : (selectedGroup?.total ?? 0)
    );

    const formattedCenterTotal = $derived(Formatter.currency(currentCenterTotal, true));

    const centerSubtext = $derived(view === 'children' ? selectedGroup?.name : periodLabel);

    const donutSelectedGroup = $derived(
        donutSelectedKey ? (categories.find((g) => g.name === donutSelectedKey) ?? null) : null
    );

    interface BarRow {
        name: string;
        total: number;
        percentage: number;
        color: string;
        group: ParentSpendingItemData | null;
    }

    const barRows = $derived.by(() => {
        if (view === 'children' && selectedGroup) {
            return selectedGroup.children.map((c) => ({
                name: c.name,
                total: c.total,
                percentage:
                    selectedGroup.total > 0
                        ? Math.round((c.total / selectedGroup.total) * 10000) / 100
                        : 0,
                color: getDecorationColor(c.color)?.oklch ?? c.color,
                group: null,
            }));
        }

        return categories.map((g) => ({
            name: g.name,
            total: g.total,
            percentage: g.percentage,
            color: getDecorationColor(g.color)?.oklch ?? g.color,
            group: g,
        }));
    });

    // --- Actions ---

    function drillDown(group: ParentSpendingItemData) {
        if (group.children.length === 0) return;

        selectedGroup = group;
        view = 'children';
        donutSelectedKey = null;
    }

    function goBack() {
        view = 'parent';
        selectedGroup = null;
        donutSelectedKey = null;
    }
</script>

<div class="space-y-4">
    {#if view === 'children' && selectedGroup}
        <Button color="light" onclick={goBack} size="sm" variant="outline">
            <i class="iconify solar--alt-arrow-left-line-duotone"></i>
            Back
        </Button>
    {/if}

    {#if variant === 'donut'}
        <DonutChart
            {centerSubtext}
            centerText={formattedCenterTotal}
            data={currentSlices}
            {emptyMessage}
            bind:selectedKey={donutSelectedKey} />

        {#if view === 'parent' && donutSelectedGroup}
            <Button
                class="w-full"
                color="light"
                onclick={() => drillDown(donutSelectedGroup)}
                size="sm"
                variant="outline">
                See Detail
            </Button>
        {/if}

        {#if view === 'parent'}
            {#if categories.length > 0}
                <ul class="space-y-0.5 text-sm">
                    {#each categories as group, i (i)}
                        <li class="flex">
                            {@render listContent(group, () => drillDown(group))}
                        </li>
                    {/each}
                </ul>
            {/if}
        {:else if selectedGroup}
            {#if selectedGroup.children.length > 0}
                <ul class="space-y-0.5 text-sm">
                    {#each selectedGroup.children as item, i (i)}
                        <li class="flex">
                            {@render listContent(item)}
                        </li>
                    {/each}
                </ul>
            {/if}
        {/if}
    {:else}
        {#if barRows.length === 0}
            <div class="flex min-h-24 items-center justify-center text-base-content/40">
                <p class="text-sm">{emptyMessage}</p>
            </div>
        {:else}
            <div class="space-y-3">
                {#each barRows as row (row.name)}
                    <div>
                        <div class="mb-1.5 flex items-center justify-between gap-4 text-sm">
                            {#if row.group}
                                <button
                                    class="min-w-0 flex-1 cursor-pointer truncate text-left font-medium hover:opacity-80"
                                    onclick={() => drillDown(row.group!)}>
                                    {row.name}
                                </button>
                            {:else}
                                <span class="min-w-0 flex-1 truncate font-medium">{row.name}</span>
                            {/if}
                            <div class="flex shrink-0 items-center gap-2">
                                <span class="text-xs text-base-content/50">{row.percentage}%</span>
                                <span class="font-medium">
                                    <CurrencyAmount value={row.total} />
                                </span>
                            </div>
                        </div>
                        <div class="h-2 w-full overflow-hidden rounded-full bg-base-200">
                            <div
                                style="width: {Math.min(
                                    Math.max(row.percentage, 0),
                                    100
                                )}%; background: {row.color};"
                                class="h-full rounded-full transition-all"
                                aria-hidden="true">
                            </div>
                        </div>
                    </div>
                {/each}
            </div>
        {/if}
    {/if}
</div>

{#snippet listContent(spendingItem, onclick?: () => void)}
    {@const isButton = typeof onclick === 'function' && onclick}

    <!-- svelte-ignore a11y_no_static_element_interactions -->
    <svelte:element
        this={isButton ? 'button' : 'div'}
        class={[
            '-mx-2.5 flex min-w-0 flex-1 items-center gap-2 rounded-md px-2.5 py-2 text-left hover:bg-popover',
            '[&_button]:cursor-pointer',
        ]}
        onclick={isButton ? onclick : undefined}>
        <span
            style:background={getDecorationColor(spendingItem.color)?.oklch}
            class="inline-block size-3 shrink-0 rounded-sm"></span>
        <span class="grow truncate">{spendingItem.name}</span>

        <div class="space-x-1 text-right">
            <CurrencyAmount class="font-semibold" value={spendingItem.total} />

            <!-- <span class="text-foreground/70">{spendingItem?.percentage}%</span> -->
        </div>
    </svelte:element>
{/snippet}
