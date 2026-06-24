<script lang="ts">
    import Button from '@components/ui/button.svelte';
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    interface ChildItem {
        category_id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }

    interface ParentGroup {
        category_id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
        children: ChildItem[];
    }

    let {
        categories,
        periodTotal,
        periodLabel,
        emptyMessage = 'No spending data for this period',
    }: {
        categories: ParentGroup[];
        periodTotal: number;
        periodLabel?: string;
        emptyMessage?: string;
    } = $props();

    // View state: 'parent' shows grouped parents, 'children' shows one parent's breakdown
    let view: 'parent' | 'children' = $state('parent');
    let selectedGroup: ParentGroup | null = $state(null);
    let donutSelectedKey = $state<string | null>(null);

    // --- Derived data ---

    const currentSlices = $derived(
        view === 'parent'
            ? categories.map((g) => ({ name: g.name, value: g.total, color: g.color }))
            : selectedGroup!.children.map((c) => ({ name: c.name, value: c.total, color: c.color }))
    );

    const currentCenterTotal = $derived(
        view === 'parent' ? periodTotal : (selectedGroup?.total ?? 0)
    );

    const formattedCenterTotal = $derived(
        currentCenterTotal.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        })
    );

    const centerSubtext = $derived(view === 'children' ? selectedGroup?.name : periodLabel);

    const donutSelectedGroup = $derived(
        donutSelectedKey ? (categories.find((g) => g.name === donutSelectedKey) ?? null) : null
    );

    // --- Actions ---

    function drillDown(group: ParentGroup) {
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
        <Button class="btn-sm" color="light" onclick={goBack} variant="outline">
            <span>←</span>
            <span>{selectedGroup.name}</span>
        </Button>
    {/if}

    <DonutChart
        {centerSubtext}
        centerText={formattedCenterTotal}
        data={currentSlices}
        {emptyMessage}
        bind:selectedKey={donutSelectedKey} />

    {#if view === 'parent' && donutSelectedGroup}
        <Button
            class="btn-sm w-full"
            color="light"
            onclick={() => drillDown(donutSelectedGroup)}
            variant="outline">
            See Detail
        </Button>
    {/if}

    {#if view === 'parent'}
        {#if categories.length > 0}
            <ul class="space-y-2">
                {#each categories as group, i (i)}
                    <li class="flex items-center justify-between text-sm">
                        <button
                            class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-left hover:opacity-80"
                            onclick={() => drillDown(group)}>
                            <span
                                style="background-color: {group.color}"
                                class="inline-block size-2.5 shrink-0 rounded-[2px]"></span>
                            <span class="truncate">{group.name}</span>
                        </button>
                        <div class="ml-4 shrink-0 text-right">
                            <span class="font-mono font-medium"
                                >{group.total.toLocaleString('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0,
                                })}</span>
                            <span class="ml-1 text-base-content/50">{group.percentage}%</span>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}
    {:else if selectedGroup}
        {#if selectedGroup.children.length > 0}
            <ul class="space-y-2">
                {#each selectedGroup.children as item, i (i)}
                    <li class="flex items-center justify-between text-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <span
                                style="background-color: {item.color}"
                                class="inline-block size-2.5 shrink-0 rounded-[2px]"></span>
                            <span class="truncate">{item.name}</span>
                        </div>
                        <div class="ml-4 shrink-0 text-right">
                            <span class="font-mono font-medium"
                                >{item.total.toLocaleString('id-ID', {
                                    style: 'currency',
                                    currency: 'IDR',
                                    maximumFractionDigits: 0,
                                })}</span>
                            <span class="ml-1 text-base-content/50"
                                >{selectedGroup.total > 0
                                    ? Math.round((item.total / selectedGroup.total) * 10000) / 100
                                    : 0}%</span>
                        </div>
                    </li>
                {/each}
            </ul>
        {/if}
    {/if}
</div>
