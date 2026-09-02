<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ReportController from '@wayfinder/App/Http/Controllers/ReportController';

    import TrendChart from '@components/module/report/trend-chart.svelte';
    import Badge from '@components/ui/badge.svelte';
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

    let {
        account,
        trend,
        months,
    }: {
        account: App.Models.Account;
        trend: TrendReport;
        months: number;
    } = $props();

    function setMonths(m: number) {
        router.visit(ReportController.trend.url({ account: account.id, query: { months: m } }), {
            preserveScroll: true,
        });
    }

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }

    const totalIncome = $derived(trend.months.reduce((s, m) => s + m.income, 0));
    const totalExpense = $derived(trend.months.reduce((s, m) => s + m.expense, 0));
    const totalNet = $derived(totalIncome - totalExpense);
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
            <h1 class="text-xl font-bold">Income vs Expense</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <!-- Month selector -->
    <div class="mb-4 flex gap-2">
        {#each [3, 6, 12] as m (m)}
            <Button
                class="flex-1 btn-sm"
                color={months === m ? 'primary' : 'light'}
                onclick={() => setMonths(m)}
                variant={months === m ? 'solid' : 'outline'}>
                {m}M
            </Button>
        {/each}
    </div>

    <!-- Trend chart -->
    <Card class="mb-4">
        <TrendChart months={trend.months} />
    </Card>

    <!-- Totals summary -->
    <div class="mb-4 grid grid-cols-3 gap-3">
        <Card class="text-center">
            <p class="text-xs text-base-content/50">Total Income</p>
            <p class="font-mono text-sm font-bold text-success">{formatIDR(totalIncome)}</p>
        </Card>
        <Card class="text-center">
            <p class="text-xs text-base-content/50">Total Expense</p>
            <p class="font-mono text-sm font-bold text-error">{formatIDR(totalExpense)}</p>
        </Card>
        <Card class="text-center">
            <p class="text-xs text-base-content/50">Net</p>
            <p class="font-mono text-sm font-bold {totalNet >= 0 ? 'text-success' : 'text-error'}">
                {formatIDR(totalNet)}
            </p>
        </Card>
    </div>

    <!-- Per-month breakdown table -->
    <Card title="Monthly Breakdown">
        <div class="overflow-x-auto">
            <table class="table w-full table-sm">
                <thead>
                    <tr class="text-xs text-base-content/50">
                        <th>Month</th>
                        <th class="text-right">Income</th>
                        <th class="text-right">Expense</th>
                        <th class="text-right">Net</th>
                        <th class="text-right">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    {#each [...trend.months].reverse() as m (m.year + '-' + m.month)}
                        <tr class="text-sm">
                            <td class="font-medium">
                                {new Date(m.year, m.month - 1).toLocaleString('default', {
                                    month: 'short',
                                    year: '2-digit',
                                })}
                            </td>
                            <td class="text-right font-mono text-success">{formatIDR(m.income)}</td>
                            <td class="text-right font-mono text-error">{formatIDR(m.expense)}</td>
                            <td
                                class="text-right font-mono {m.net >= 0
                                    ? 'text-success'
                                    : 'text-error'}">{formatIDR(m.net)}</td>
                            <td class="text-right">
                                <Badge
                                    color={m.surplus_rate >= 20
                                        ? 'success'
                                        : m.surplus_rate >= 0
                                          ? 'warning'
                                          : 'error'}
                                    variant="soft">
                                    {m.surplus_rate.toFixed(0)}%
                                </Badge>
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
    </Card>
</div>
