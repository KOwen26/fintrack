<script lang="ts">
    import type { CategorySpendingReportData, TransactionListData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import settings from '@wayfinder/routes/settings';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import DashboardWelcomeCard from '@components/module/dashboard/dashboard-welcome-card.svelte';
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import AppIcon from '@components/ui/app-icon.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import Separator from '@components/ui/separator.svelte';

    interface Summary {
        current_balance: number;
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
            <AppIcon />
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
    <MobilePageLayout variant="4/5">
        <div class="py-24">
            <DashboardWelcomeCard ctaUrl={AccountController.create.url()} />
        </div>
    </MobilePageLayout>
{:else}
    <MobilePageLayout variant="3/5">
        {#snippet hero()}
            <div>
                <h3 class="mb-1 text-lg font-medium text-current/70">Current Balance</h3>
                <div class="mb-2 flex items-center gap-0.5">
                    <CurrencyAmount
                        class="text-5xl font-semibold"
                        value={summary?.current_balance} />
                </div>
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
                        {categorySpending}
                        emptyMessage="No spending data for this period"
                        periodLabel="This month" />
                </ResponsiveCard>
            {/if}

            <Separator />

            <ResponsiveCard title="Recent Transactions">
                <div class="space-y-1.5">
                    <TransactionList
                        hideTotal
                        loadMoreAction={() => router.visit(TransactionController.index.url())}
                        transactions={recent_transactions} />
                </div>
            </ResponsiveCard>
        </div>
    </MobilePageLayout>
{/if}
