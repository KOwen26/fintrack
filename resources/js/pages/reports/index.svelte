<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ReportController from '@wayfinder/App/Http/Controllers/ReportController';
    import { SvelteDate } from 'svelte/reactivity';

    import CategoryLeakChart from '@components/module/report/category-leak-chart.svelte';
    import TrendChart from '@components/module/report/trend-chart.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface TrendMonth {
        year: number;
        month: number;
        income: number;
        expense: number;
        net: number;
        surplus_rate: number;
    }
    interface TrendReport {
        months: TrendMonth[];
        months_count: number;
    }
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
        trend,
        category_leak,
        from,
        to,
    }: {
        account: App.Models.Account;
        trend: TrendReport;
        category_leak: CategoryLeakReport;
        from: string;
        to: string;
    } = $props();

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }

    // Current month summary (last item in trend array)
    const latestMonth = $derived(trend.months.at(-1));

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new SvelteDate(from);
        const offset = direction === 'prev' ? -1 : 1;
        current.setMonth(current.getMonth() + offset);
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportController.index.url({
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
    <!-- Header -->
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold">Reports</h1>
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

    <!-- Current month summary cards -->
    {#if latestMonth}
        <div class="mb-4 grid grid-cols-3 gap-3">
            <Card class="text-center">
                <p class="text-xs text-base-content/50">Income</p>
                <p class="font-mono text-sm font-bold text-success">
                    {formatIDR(latestMonth.income)}
                </p>
            </Card>
            <Card class="text-center">
                <p class="text-xs text-base-content/50">Expense</p>
                <p class="font-mono text-sm font-bold text-error">
                    {formatIDR(latestMonth.expense)}
                </p>
            </Card>
            <Card class="text-center">
                <p class="text-xs text-base-content/50">Net</p>
                <p
                    class="font-mono text-sm font-bold {latestMonth.net >= 0
                        ? 'text-success'
                        : 'text-error'}">
                    {formatIDR(latestMonth.net)}
                </p>
            </Card>
        </div>
    {/if}

    <!-- Trend chart card -->
    <Card class="mb-4" title="Income vs Expense">
        {#snippet headerAction()}
            <Button
                class="btn-xs"
                color="light"
                href={ReportController.trend.url({ account: account.id })}
                variant="ghost">
                Full view
            </Button>
        {/snippet}
        <TrendChart months={trend.months} />
    </Card>

    <!-- Category leak card -->
    <Card class="mb-4" title="Top Spending Categories">
        {#snippet headerAction()}
            <Button
                class="btn-xs"
                color="light"
                href={ReportController.categoryLeak.url({
                    account: account.id,
                    query: { from, to },
                })}
                variant="ghost">
                Full view
            </Button>
        {/snippet}
        <CategoryLeakChart
            categories={category_leak.categories.slice(0, 5)}
            period_total={category_leak.period_total} />
    </Card>

    <!-- Report nav links -->
    <div class="space-y-2">
        <a
            class="flex items-center justify-between rounded-xl bg-base-200 px-4 py-3 text-sm font-medium transition-opacity active:opacity-70"
            href={ReportController.fixedVsVariable.url({
                account: account.id,
                query: { from, to },
            })}>
            <div class="flex items-center gap-2">
                <i class="iconify size-5 text-secondary solar--tuning-2-bold-duotone"></i>
                Fixed vs Variable
            </div>
            <i class="iconify size-4 text-base-content/30 solar--alt-arrow-right-line-duotone"></i>
        </a>

        <a
            class="flex items-center justify-between rounded-xl bg-base-200 px-4 py-3 text-sm font-medium transition-opacity active:opacity-70"
            href={ReportController.contributionSplit.url({
                account: account.id,
                query: { from, to },
            })}>
            <div class="flex items-center gap-2">
                <i class="iconify size-5 text-accent solar--users-group-two-rounded-bold-duotone"
                ></i>
                Contribution Split
            </div>
            <i class="iconify size-4 text-base-content/30 solar--alt-arrow-right-line-duotone"></i>
        </a>
    </div>
</div>
