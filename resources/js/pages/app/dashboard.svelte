<script lang="ts">
    import type { CategorySpendingReportData, TransactionListData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import settings from '@wayfinder/routes/settings';

    import Icon from '@assets/images/icon-transparent.png';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import BalanceHeroCard from '@components/module/dashboard/balance-hero-card.svelte';
    import DashboardWelcomeCard from '@components/module/dashboard/dashboard-welcome-card.svelte';
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import Separator from '@components/ui/separator.svelte';

    interface Summary {
        total_balance: number;
        monthly_income: number;
        monthly_expenses: number;
        monthly_savings: number;
    }

    let {
        categorySpending = null,
        summary = null,
        recent_transactions = [],
        accounts = [],
    }: {
        categorySpending?: CategorySpendingReportData | null;
        summary?: Summary | null;
        recent_transactions?: TransactionListData[];
        accounts?: App.Models.Account[];
    } = $props();
</script>

<HeaderContext>
    <div class="flex w-full items-center justify-between gap-3 md:w-auto">
        <div>
            <img class="aspect-square size-10" alt="Logo" src={Icon} />
        </div>

        <div>
            <Button
                aria-label="Open settings"
                href={settings.index.url()}
                size="icon"
                variant="ghost">
                <i class="iconify size-8 solar--settings-bold-duotone"></i>
            </Button>
        </div>
    </div>
</HeaderContext>

{#if !accounts.length}
    <MobilePageLayout variant="full">
        <DashboardWelcomeCard ctaUrl={AccountController.create.url()} />
    </MobilePageLayout>
{:else}
    <MobilePageLayout variant="3/5">
        {#snippet hero()}
            <div class="space-y-5 p-5 pt-20">
                <BalanceHeroCard
                    loading={!summary}
                    monthlyExpenses={summary?.monthly_expenses ?? 0}
                    monthlyIncome={summary?.monthly_income ?? 0}
                    totalBalance={summary?.total_balance ?? null} />
            </div>
        {/snippet}

        <div class="grid grid-cols-1 gap-6">
            <!-- Accounts -->
            <ResponsiveCard title="Accounts">
                {#snippet headerAction()}
                    <Button href={AccountController.index.url()} variant="ghost">Manage</Button>
                {/snippet}

                <div class="space-y-1.5">
                    <AccountList {accounts} hideActions mode="grid" />
                </div>
            </ResponsiveCard>

            <Separator />

            {#if categorySpending}
                <ResponsiveCard title="Spending by Category">
                    <CategorySpendingChart
                        categories={categorySpending.categories}
                        emptyMessage="No spending data for this period"
                        periodLabel="This month"
                        periodTotal={categorySpending.period_total} />
                </ResponsiveCard>
            {/if}

            <Separator />

            <ResponsiveCard title="Recent Transactions">
                <div class="space-y-1.5">
                    <TransactionList hideControl hideTotal transactions={recent_transactions} />

                    <Button
                        class="w-full"
                        color="light"
                        href={TransactionController.index.url()}
                        variant="soft">See More</Button>
                </div>
            </ResponsiveCard>
        </div>
    </MobilePageLayout>
{/if}
