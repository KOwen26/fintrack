<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ReportController from '@wayfinder/App/Http/Controllers/ReportController';
    import { SvelteDate } from 'svelte/reactivity';

    import CategoryLeakChart from '@components/module/report/category-leak-chart.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface CategoryItem {
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }
    interface CategoryLeakReport {
        categories: CategoryItem[];
        period_total: number;
        from: string;
        to: string;
    }

    let {
        account,
        category_leak,
        from,
        to,
    }: {
        account: App.Models.Account;
        category_leak: CategoryLeakReport;
        from: string;
        to: string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new SvelteDate(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportController.categoryLeak.url({
                account: account.id,
                query: {
                    from: newFrom.toISOString().slice(0, 10),
                    to: newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={ReportController.index.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 solar--arrow-left-line-duotone"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Category Breakdown</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <!-- Period nav -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
        <Button
            class="btn-circle btn-xs"
            color="light"
            onclick={() => navigatePeriod('prev')}
            variant="ghost">
            <i class="iconify size-4 solar--alt-arrow-left-line-duotone"></i>
        </Button>
        <span class="text-sm font-medium">{periodLabel}</span>
        <Button
            class="btn-circle btn-xs"
            color="light"
            onclick={() => navigatePeriod('next')}
            variant="ghost">
            <i class="iconify size-4 solar--alt-arrow-right-line-duotone"></i>
        </Button>
    </div>

    <Card class="mb-4">
        <CategoryLeakChart
            categories={category_leak.categories}
            period_total={category_leak.period_total} />
    </Card>
</div>
