<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { ComponentProps } from 'svelte';

    import AccountCard from './account-card.svelte';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import { cn } from '@utilities/shadcn';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import ToggleableGrid from '@components/data/toggleable-grid.svelte';

    interface Props {
        accounts: App.Models.Account[];
    }

    let { accounts }: Props = $props();

    let mode = $state<ComponentProps<typeof ToggleableGrid>['mode']>('list');
</script>

{#if accounts.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Create your first account"
        ctaUrl={AccountController.create.url()}
        icon="ph--wallet-bold"
        label="No accounts yet" />
{:else}
    <ToggleableGrid class="mb-3" bind:mode>
        <h6 class="text-sm font-medium text-base-content/60">
            {accounts.length} Account{accounts.length !== 1 ? 's' : ''}
        </h6>
    </ToggleableGrid>

    <div class={cn('grid gap-3', mode === 'list' ? 'grid-cols-1' : 'grid-cols-2')}>
        {#each accounts as account (account.id)}
            <Link href={AccountController.show.url({ account: account.id })}>
                <AccountCard {account} />
            </Link>
        {/each}

        <!-- Add Account Placeholder -->
        <div class="col-span-full">
            <Link
                class="flex min-h-25 cursor-pointer items-center justify-center rounded-xl border-2 border-dashed border-base-200 bg-card transition-colors hover:border-primary/50 hover:bg-primary/5"
                href={AccountController.create.url()}>
                <div class="text-center">
                    <i class="mx-auto mb-1 iconify block size-5 text-base-content/50 ph--plus-bold"
                    ></i>
                    <span class="text-sm font-medium text-base-content/50">Add Account</span>
                </div>
            </Link>
        </div>
    </div>
{/if}
