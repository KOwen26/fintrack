<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import Formatter from '@utilities/formatter';

    import PageSection from '@components/layouts/page-section.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import StatCard from '@components/ui/cards/stat-card.svelte';

    interface Summary {
        total_balance: number;
        total_accounts: number;
        credit_utilization_percentage: number | null;
        oldest_account_years: number | null;
    }

    let {
        accounts,
        archived_accounts = [],
        summary,
    }: {
        accounts: App.Models.Account[];
        archived_accounts: App.Models.Account[];
        summary: Summary;
    } = $props();
</script>

<DashboardPageHeader class="hidden sm:block" title="Accounts">
    {#snippet actions()}
        <Button color="primary" href={AccountController.create.url()}>
            <i class="iconify size-5 ph--plus-bold"></i>
            Add
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <StatCard
            color="primary"
            icon="ph--wallet-bold"
            label="Total Balance"
            value={summary.total_balance} />

        <StatCard
            color="info"
            format="number"
            icon="ph--bank-bold"
            label="Total Accounts"
            value={summary.total_accounts} />

        <!-- <StatCard
            color="warning"
            icon="ph--credit-card-bold"
            label="Credit Utilisation"
            value={summary.credit_utilization_percentage !== null
                ? `${summary.credit_utilization_percentage}%`
                : null} />

        {let oldestLabel = $derived.by(() => {
            if (summary.oldest_account_years === null) return null;
            if (summary.oldest_account_years < 1) return 'Less than a year';

            return `${summary.oldest_account_years} year${summary.oldest_account_years > 1 ? 's' : ''}`;
        })}

        <StatCard
            color="success"
            icon="ph--clock-bold"
            label="Oldest Account"
            value={oldestLabel} /> -->
    </div>
</PageSection>

<PageSection class="space-y-5">
    <AccountList {accounts} />

    <!-- Add Account Placeholder -->
    <div class="col-span-full">
        <Link
            class="flex min-h-38 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-base-200 bg-card transition-colors hover:border-primary/50 hover:bg-primary/5"
            href={AccountController.create.url()}>
            <div class="text-center">
                <i class="mx-auto mb-1 iconify block size-5 text-base-content/50 ph--plus-bold"></i>
                <span class="text-sm font-medium text-base-content/50">Add Account</span>
            </div>
        </Link>
    </div>

    {#if archived_accounts.length > 0}
        {@render ArchivedAccounts()}
    {/if}
</PageSection>

{#snippet ArchivedAccounts()}
    <details class="mt-6 rounded-xl bg-card">
        <summary
            class="flex cursor-pointer items-center justify-between p-4 text-sm font-medium text-base-content/60">
            <span>Archived ({archived_accounts.length})</span>
            <i class="iconify size-4 text-base-content/40 ph--caret-down-bold"></i>
        </summary>
        <div class="space-y-2 px-4 pb-4">
            {#each archived_accounts as acct (acct.id)}
                <div class="flex items-center justify-between rounded-lg bg-base-200 p-3">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-base-300">
                            <i class="iconify size-4 text-base-content/50 ph--bank-bold"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-base-content/60">{acct.name}</p>
                            <p class="text-xs text-base-content/40">
                                Final balance: {Formatter.currency(acct.current_balance)}
                            </p>
                        </div>
                    </div>
                    <Link
                        class="text-xs font-medium text-primary transition-colors hover:text-primary/80"
                        as="button"
                        href={AccountController.restore.url({ account: acct.id })}
                        method="post">
                        Restore
                    </Link>
                </div>
            {/each}
        </div>
    </details>
{/snippet}
