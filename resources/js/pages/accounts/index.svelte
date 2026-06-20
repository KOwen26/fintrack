<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountCard from '@components/module/account/account-card.svelte';
    import Button from '@components/ui/button.svelte';
    import ToggleableCard from '@components/ui/cards/toggleable-card.svelte';

    let { accounts }: { accounts: App.Models.Account[] } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <h1 class="text-xl font-bold">Accounts</h1>
        <Button color="primary" href={AccountController.create.url()} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    {#if accounts.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/50">
            <i class="iconify mb-3 size-12 ph--wallet-bold"></i>
            <p class="text-sm">No accounts yet</p>
            <Button class="mt-4" color="primary" href={AccountController.create.url()} size="sm">
                Create your first account
            </Button>
        </div>
    {:else}
        <ToggleableCard>
            {#each accounts as account (account.id)}
                <Link href={AccountController.show.url({ account: account.id })}>
                    <AccountCard {account} />
                </Link>
            {/each}
        </ToggleableCard>
    {/if}
</div>
