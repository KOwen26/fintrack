<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import Formatter from '@utilities/formatter';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import AccountsSummaryCard from '@components/module/account/accounts-summary-card.svelte';
    import BaseAccountCard from '@components/module/account/base-account-card.svelte';

    interface Summary {
        total_balance: number;
        total_accounts: number;
        oldest_account_years: number | null;
        available_balance: number;
        investment_balance: number;
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

<MobilePageLayout variant="4/5">
    {#snippet hero()}
        <AccountsSummaryCard {summary} />
    {/snippet}

    <div class="space-y-4">
        <AccountList {accounts} />

        <Link class="block cursor-pointer" href={AccountController.create.url()}>
            <BaseAccountCard variant="create" />
        </Link>

        {#if archived_accounts.length > 0}
            {@render ArchivedAccounts()}
        {/if}
    </div>
</MobilePageLayout>

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
