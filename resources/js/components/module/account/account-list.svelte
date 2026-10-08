<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { ComponentProps } from 'svelte';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import { cn } from '@utilities/shadcn';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import ToggleableGrid from '@components/data/toggleable-grid.svelte';
    import AccountCard from '@components/module/account/account-card.svelte';
    import Link from '@components/ui/link.svelte';

    interface Props {
        accounts: App.Models.Account[];
        mode?: ComponentProps<typeof ToggleableGrid>['mode'];
        hideActions?: boolean;
    }

    let { accounts, mode = 'list', hideActions = false }: Props = $props();
</script>

{#if accounts.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Create your first account"
        ctaUrl={AccountController.create.url()}
        icon="solar--wallet-bold-duotone"
        label="No accounts yet">
        <!-- {#snippet cta()}
            <div class="w-full">
                <Link class="block cursor-pointer" href={AccountController.create.url()}>
                    <BaseAccountCard variant="create" />
                </Link>
            </div>
        {/snippet} -->
    </EmptyItemPlaceholder>
{:else}
    {#if !hideActions}
        <ToggleableGrid class="mb-3" bind:mode>
            <h6 class="text-sm font-medium text-base-content/60">
                {accounts.length} Account{accounts.length !== 1 ? 's' : ''}
            </h6>
        </ToggleableGrid>
    {/if}

    <div class={cn('grid gap-3 md:gap-6', mode === 'list' ? 'grid-cols-1' : 'grid-cols-2')}>
        {#each accounts as account (account.id)}
            <Link href={AccountController.show.url({ account: account.id })}>
                <AccountCard {account} hideActions hideEdit hideFooter />
            </Link>
        {/each}
    </div>
{/if}
