<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import Formatter from '@utilities/formatter';

    import PageSection from '@components/layouts/page-section.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import BaseAccountCard from '@components/module/account/base-account-card.svelte';
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
            <i class="iconify size-5 solar--add-bold-duotone"></i>
            Add
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4">
        <StatCard
            class="col-span-full"
            color="primary"
            icon="solar--wallet-bold-duotone"
            label="Total Balance"
            value={summary.total_balance} />

        <!-- <StatCard
            color="info"
            format="number"
            icon="solar--banknote-2-bold-duotone"
            label="Total Accounts"
            value={summary.total_accounts} /> -->

        <!-- <StatCard
            color="warning"
            icon="solar--card-bold-duotone"
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
            icon="solar--clock-circle-bold-duotone"
            label="Oldest Account"
            value={oldestLabel} /> -->
    </div>
</PageSection>

<PageSection class="space-y-5">
    <AccountList {accounts} />

    <!-- Add Account Placeholder -->
    <div class="col-span-full">
        <Link class="block cursor-pointer" href={AccountController.create.url()}>
            <BaseAccountCard variant="create" />
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
            <i class="iconify size-4 text-base-content/40 solar--alt-arrow-down-line-duotone"></i>
        </summary>
        <div class="space-y-2 px-4 pb-4">
            {#each archived_accounts as acct (acct.id)}
                <div class="flex items-center justify-between rounded-lg bg-base-200 p-3">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 items-center justify-center rounded-lg bg-base-300">
                            <i
                                class="iconify size-4 text-base-content/50 solar--banknote-2-bold-duotone"
                            ></i>
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
