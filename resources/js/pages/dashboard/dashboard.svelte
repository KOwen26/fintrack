<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import settings from '@wayfinder/routes/settings';

    import Formatter from '@utilities/formatter';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import BalanceHeroCard from '@components/module/dashboard/balance-hero-card.svelte';
    import DashboardWelcomeCard from '@components/module/dashboard/dashboard-welcome-card.svelte';
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import { TYPE_STYLE } from '@components/module/transaction/transaction-list-item.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import StatCard from '@components/ui/cards/stat-card.svelte';

    /* ── Types ───────────────────────────────────────────── */

    interface Summary {
        total_balance: number;
        monthly_income: number;
        monthly_expenses: number;
        monthly_savings: number;
    }

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

    interface CategorySpendingReport {
        categories: ParentGroup[];
        period_total: number;
        from: string;
        to: string;
    }

    /* ── Props ───────────────────────────────────────────── */

    let {
        category_spending = null,
        summary = null,
        recent_transactions = [],
        accounts = [],
    }: {
        category_spending?: CategorySpendingReport | null;
        summary?: Summary | null;
        recent_transactions?: App.Models.Transaction[];
        accounts?: App.Models.Account[];
    } = $props();

    /* ── Derived ─────────────────────────────────────────── */

    const incomePct = $derived(
        summary && summary.monthly_expenses > 0
            ? Math.round((summary.monthly_income / summary.monthly_expenses) * 100 - 100)
            : 0
    );

    const expensePct = $derived(
        summary && summary.monthly_income > 0
            ? Math.round((summary.monthly_expenses / summary.monthly_income) * 100 - 100)
            : 0
    );

    const savingsRate = $derived(
        summary && summary.monthly_income > 0
            ? Math.round((summary.monthly_savings / summary.monthly_income) * 100)
            : 0
    );

    const currentDate = $derived(
        new Date().toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        })
    );

    const leftToSpend = $derived(summary ? summary.monthly_income - summary.monthly_expenses : 0);

    const spendRate = $derived(
        summary && summary.monthly_income > 0
            ? Math.round((summary.monthly_expenses / summary.monthly_income) * 100)
            : null
    );

    /* ── Budget helpers (derived from category spending) ─── */

    // Budget targets derived from total expenses – shows each category as share of total
    const budgetItems = $derived(
        category_spending?.categories?.map((cat) => ({
            name: cat.name,
            spent: cat.total,
            percentage: cat.percentage,
            color: getDecorationColor(cat.color)?.oklch ?? cat.color,
        })) ?? []
    );

    /* ── Week trend data ─────────────────────────────────── */

    const weekDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    let trendData = $state(
        weekDays.map((day) => ({
            day,
            income: 0.3 + Math.random() * 0.5,
            expense: 0.2 + Math.random() * 0.6,
        }))
    );
</script>

<HeaderContext>
    <div class="flex w-full items-center justify-end gap-3 md:w-auto">
        <Button size="icon" aria-label="Open settings" href={settings.index.url()} variant="ghost">
            <i class="iconify size-6 solar--settings-bold-duotone"></i>
        </Button>
    </div>
</HeaderContext>

{#if !accounts.length}
    <MobilePageLayout variant="full">
        <DashboardWelcomeCard ctaUrl={AccountController.create.url()} />
    </MobilePageLayout>
{:else}
    <MobilePageLayout variant="3/5">
        {#snippet hero()}
            <div class="space-y-5 p-5">
                <!-- ══════════════════════════════════════════════════ -->
                <!-- Balance Hero -->
                <!-- ══════════════════════════════════════════════════ -->
                <BalanceHeroCard
                    loading={!summary}
                    monthlyExpenses={summary?.monthly_expenses ?? 0}
                    monthlyIncome={summary?.monthly_income ?? 0}
                    totalBalance={summary?.total_balance ?? null} />

                <!-- ══════════════════════════════════════════════════ -->
                <!-- Quick Stats -->
                <!-- ══════════════════════════════════════════════════ -->
                <div class="grid grid-cols-2 gap-3 md:gap-4">
                    <StatCard
                        color="success"
                        icon="trending-up"
                        label="Income"
                        loading={!summary}
                        trend={summary
                            ? {
                                  direction: incomePct > 0 ? 'up' : 'down',
                                  value: Math.abs(incomePct),
                                  label: `${incomePct > 0 ? '↑' : '↓'} ${Math.abs(incomePct)}% vs expenses`,
                              }
                            : null}
                        value={summary?.monthly_income ?? null} />
                    <StatCard
                        color="error"
                        icon="trending-down"
                        label="Expenses"
                        loading={!summary}
                        trend={summary
                            ? {
                                  direction: expensePct > 0 ? 'up' : 'down',
                                  value: Math.abs(expensePct),
                                  label: `${expensePct > 0 ? '↑' : '↓'} ${Math.abs(expensePct)}% vs income`,
                              }
                            : null}
                        value={summary?.monthly_expenses ?? null} />
                    <StatCard
                        color="info"
                        icon="piggy-bank"
                        label="Savings"
                        loading={!summary}
                        trend={summary
                            ? {
                                  direction: savingsRate > 0 ? 'up' : 'down',
                                  value: savingsRate,
                                  label: `${savingsRate}% savings rate`,
                              }
                            : null}
                        value={summary?.monthly_savings ?? null} />
                    <StatCard
                        color="warning"
                        icon="clock"
                        label="Left to Spend"
                        loading={!summary}
                        trend={summary
                            ? {
                                  direction: leftToSpend > 0 ? 'up' : 'down',
                                  value: Math.abs(leftToSpend),
                                  label:
                                      spendRate !== null
                                          ? `${spendRate}% of income spent`
                                          : 'Tracking this month',
                              }
                            : null}
                        value={summary ? leftToSpend : null} />
                </div>
            </div>
        {/snippet}

        <div class="space-y-4">
            <!-- ══════════════════════════════════════════════════ -->
            <!-- Spend Trend (bar chart) -->
            <!-- ══════════════════════════════════════════════════ -->
            <Card>
                {#snippet header()}
                    <h2 class="text-sm font-semibold">Spend Trend</h2>
                {/snippet}
                {#snippet headerAction()}
                    <span class="text-xs text-base-content/50">{currentDate}</span>
                {/snippet}

                <div class="flex items-end gap-1.5 pt-1">
                    {#each trendData as item, i (i)}
                        <div class="flex flex-1 flex-col items-center gap-1">
                            <div
                                style="height: {item.expense *
                                    100}%; background: oklch(from var(--color-primary) l c h / 0.2);"
                                class="w-full rounded-t-sm">
                            </div>
                            <div
                                style="height: {item.income *
                                    100}%; background: var(--color-success);"
                                class="w-full rounded-t-sm">
                            </div>
                            <span class="mt-1 text-[10px] text-base-content/50">{item.day}</span>
                        </div>
                    {/each}
                </div>
                <div class="mt-3 flex items-center gap-4 text-xs text-base-content/50">
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-success"></span> Income
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span
                            style="background: oklch(from var(--color-primary) l c h / 0.2);"
                            class="size-2.5 rounded-sm"></span>
                        Expenses
                    </span>
                </div>
            </Card>

            <!-- ══════════════════════════════════════════════════ -->
            <!-- Budget Overview -->
            <!-- ══════════════════════════════════════════════════ -->

            {#if budgetItems.length > 0}
                <Card>
                    {#snippet header()}
                        <h2 class="text-sm font-semibold">Spending by Category</h2>
                    {/snippet}
                    {#snippet headerAction()}
                        {#if category_spending?.period_total}
                            <span class="text-xs text-base-content/50"
                                >Total: {Formatter.currency(category_spending.period_total)}</span>
                        {/if}
                    {/snippet}

                    <div class="space-y-3">
                        {#each budgetItems as item, i (i)}
                            <div>
                                <div class="mb-1.5 flex items-center justify-between text-sm">
                                    <span class="font-medium">{item.name}</span>
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-base-content/50"
                                            >{item.percentage}%</span>
                                        <span class="font-medium"
                                            >{Formatter.currency(item.spent)}</span>
                                    </div>
                                </div>
                                <div class="h-2 w-full rounded-full bg-base-200">
                                    <div
                                        style="width: {item.percentage}%; background: {item.color};"
                                        class="h-full rounded-full transition-all">
                                    </div>
                                </div>
                            </div>
                        {/each}
                    </div>
                </Card>
            {/if}

            <!-- ══════════════════════════════════════════════════ -->
            <!-- Recent Transactions -->
            <!-- ══════════════════════════════════════════════════ -->

            {#if recent_transactions.length > 0}
                <Card>
                    {#snippet header()}
                        <h2 class="text-sm font-semibold">Recent Transactions</h2>
                    {/snippet}
                    {#snippet headerAction()}
                        <Button
                            class="text-xs"
                            href={TransactionController.index.url()}
                            size="sm"
                            variant="link">
                            View All
                        </Button>
                    {/snippet}

                    <div class="-mx-5 divide-y divide-base-200">
                        {#each recent_transactions as tx (tx.id)}
                            {@const style = TYPE_STYLE[tx.type]}
                            <a
                                class="flex cursor-pointer items-center gap-3 px-5 py-3 transition-colors hover:bg-base-200/50"
                                href={TransactionController.show.url({ transaction: tx.id })}>
                                <div
                                    style:background={style.bg}
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg">
                                    {#if tx.category?.decorations?.icon}
                                        <i
                                            style:color={style.color}
                                            class="iconify size-4 {tx.category.decorations.icon}"
                                        ></i>
                                    {:else}
                                        <i
                                            style:color={style.color}
                                            class="iconify size-4 solar--dollar-minimalistic-bold-duotone"
                                        ></i>
                                    {/if}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {tx.description || tx.category?.name || style.label}
                                    </p>
                                    <p class="text-xs text-base-content/50">
                                        {tx.category?.name ?? style.label}
                                        {#if tx.account}
                                            · {tx.account.name}
                                        {/if}
                                    </p>
                                </div>
                                <span
                                    style:color={style.color}
                                    class="shrink-0 text-sm font-semibold">
                                    {tx.flow === 'inflow' ? '+' : '−'}{Formatter.currency(
                                        tx.amount,
                                        true
                                    )}
                                </span>
                            </a>
                        {/each}
                    </div>
                </Card>
            {/if}

            <!-- ══════════════════════════════════════════════════ -->
            <!-- Accounts Overview + Category Spending (2-col on lg) -->
            <!-- ══════════════════════════════════════════════════ -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 lg:gap-6">
                <!-- Accounts -->
                <Card>
                    {#snippet header()}
                        <h2 class="text-sm font-semibold">Accounts</h2>
                    {/snippet}
                    {#snippet headerAction()}
                        <Button
                            class="text-xs"
                            href={AccountController.index.url()}
                            size="sm"
                            variant="link">
                            Manage
                        </Button>
                    {/snippet}

                    <div class="space-y-3">
                        {#each accounts as account (account.id)}
                            {@const decoColor = getDecorationColor(account.decorations?.color)}
                            <a
                                class="block rounded-lg border border-base-200 p-4 transition-shadow hover:shadow-sm"
                                href={AccountController.show.url({ account: account.id })}>
                                <div class="mb-2 flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium">{account.name}</p>
                                        {#if account.provider}
                                            <p class="text-xs text-base-content/50">
                                                {account.provider.name}
                                            </p>
                                        {/if}
                                    </div>
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg bg-base-200">
                                        {#if decoColor}
                                            <i
                                                style="color: {decoColor.hex};"
                                                class="iconify size-4"></i>
                                        {:else}
                                            <i
                                                class="iconify size-4 text-base-content/50 solar--banknote-2-bold-duotone"
                                            ></i>
                                        {/if}
                                    </div>
                                </div>
                                <p class="text-lg font-bold tracking-tight">
                                    {Formatter.currency(account.current_balance ?? 0)}
                                </p>
                            </a>
                        {/each}

                        <!-- Add Account -->
                        <a
                            class="flex min-h-[88px] cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-base-300 transition-colors hover:border-base-content/30"
                            href={AccountController.create.url()}>
                            <div class="text-center">
                                <i
                                    class="mx-auto mb-1 iconify size-5 text-base-content/50 solar--add-bold-duotone"
                                ></i>
                                <p class="text-xs font-medium text-base-content/50">Add Account</p>
                            </div>
                        </a>
                    </div>
                </Card>

                <!-- Category Spending (existing chart) -->
                {#if category_spending}
                    <Card>
                        {#snippet header()}
                            <h2 class="text-sm font-semibold">Spending by Category</h2>
                        {/snippet}

                        <CategorySpendingChart
                            categories={category_spending.categories}
                            emptyMessage="No spending data for this period"
                            periodLabel="This month"
                            periodTotal={category_spending.period_total} />
                    </Card>
                {/if}
            </div>
        </div>
    </MobilePageLayout>
{/if}
