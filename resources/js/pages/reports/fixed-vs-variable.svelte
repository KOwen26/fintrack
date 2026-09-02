<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ReportController from '@wayfinder/App/Http/Controllers/ReportController';
    import { SvelteDate } from 'svelte/reactivity';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface FixedVariable {
        fixed_total: number;
        variable_total: number;
        fixed_pct: number;
        variable_pct: number;
        safety_margin: number;
        from: string;
        to: string;
    }

    let {
        account,
        fixed_vs_variable,
        from,
        to,
    }: {
        account: App.Models.Account;
        fixed_vs_variable: FixedVariable;
        from: string;
        to: string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new SvelteDate(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportController.fixedVsVariable.url({
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

    const grandTotal = $derived(fixed_vs_variable.fixed_total + fixed_vs_variable.variable_total);

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }
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
            <h1 class="text-xl font-bold">Fixed vs Variable</h1>
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

    {#if grandTotal === 0}
        <div
            class="flex flex-col items-center justify-center py-16 text-center text-base-content/50">
            <i class="mb-3 iconify size-12 solar--tuning-2-bold-duotone"></i>
            <p class="font-semibold">No expense data</p>
            <p class="mt-1 text-sm">No expense or fee transactions found for this period.</p>
        </div>
    {:else}
        <!-- Stacked bar -->
        <Card class="mb-4">
            <p class="mb-3 text-sm font-medium">Spend composition</p>
            <div class="flex h-8 w-full overflow-hidden rounded-full">
                <div
                    style="width: {fixed_vs_variable.fixed_pct}%"
                    class="bg-error transition-all"
                    title="Fixed: {fixed_vs_variable.fixed_pct}%">
                </div>
                <div
                    style="width: {fixed_vs_variable.variable_pct}%"
                    class="bg-info transition-all"
                    title="Variable: {fixed_vs_variable.variable_pct}%">
                </div>
            </div>
            <div class="mt-3 flex justify-between text-xs">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block size-2.5 rounded-[2px] bg-error"></span>
                    Fixed ({fixed_vs_variable.fixed_pct}%)
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="inline-block size-2.5 rounded-[2px] bg-info"></span>
                    Variable ({fixed_vs_variable.variable_pct}%)
                </div>
            </div>
        </Card>

        <!-- Stats -->
        <div class="mb-4 grid grid-cols-2 gap-3">
            <Card>
                <p class="text-xs text-base-content/50">Fixed costs</p>
                <p class="font-mono font-bold text-error">
                    {formatIDR(fixed_vs_variable.fixed_total)}
                </p>
                <p class="mt-1 text-xs text-base-content/40">Rent, utilities, subscriptions</p>
            </Card>
            <Card>
                <p class="text-xs text-base-content/50">Variable</p>
                <p class="font-mono font-bold text-info">
                    {formatIDR(fixed_vs_variable.variable_total)}
                </p>
                <p class="mt-1 text-xs text-base-content/40">Dining, shopping, entertainment</p>
            </Card>
        </div>

        <!-- Safety margin -->
        <Card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium">Spending Flexibility</p>
                    <p class="mt-0.5 text-xs text-base-content/50">
                        How much of your spend is discretionary (can be reduced if needed)
                    </p>
                </div>
                <Badge
                    color={fixed_vs_variable.safety_margin >= 50
                        ? 'success'
                        : fixed_vs_variable.safety_margin >= 25
                          ? 'warning'
                          : 'error'}
                    variant="soft">
                    {fixed_vs_variable.safety_margin.toFixed(0)}%
                </Badge>
            </div>
        </Card>
    {/if}
</div>
