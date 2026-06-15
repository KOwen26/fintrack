<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { inertia } from '@inertiajs/svelte';
    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';

    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Accounts</h1>
        <Button color="primary" href={AccountsController.create.url()} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if accounts.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--wallet-bold"></i>
            <p class="text-sm">No accounts yet</p>
            <Button class="mt-4" color="primary" href={AccountsController.create.url()} size="sm">
                Create your first account
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each accounts as account (account.id)}
                <a href={AccountsController.show.url({ account: account.id })} use:inertia>
                    <Card wrapperClass="transition-transform active:scale-95">
                        <div class="flex items-center justify-between">
                            <div class="space-y-1">
                                <p class="font-semibold">{account.name}</p>
                                <div class="flex items-center gap-1">
                                    <AccountTypeBadge type={account.type} />
                                    <AccountAccessTypeBadge type={account.access_type} />
                                    <span class="text-xs text-base-content/50"
                                        >{account.currency}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-base-content/50">Initial Balance</p>
                                <p class="font-mono font-semibold">
                                    {Number(account.initial_balance).toLocaleString('id-ID')}
                                </p>
                            </div>
                        </div>
                    </Card>
                </a>
            {/each}
        </div>
    {/if}
</div>
