<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { ComponentProps } from 'svelte';

    import AccountCard from './account-card.svelte';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import { cn } from '@utilities/shadcn';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import ToggleableCard from '@components/ui/cards/toggleable-card.svelte';

    interface Props {
        accounts: App.Models.Account[];
    }

    let { accounts }: Props = $props();

    let mode = $state<ComponentProps<typeof ToggleableCard>['mode']>('list');
</script>

{#if accounts.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Create your first account"
        ctaUrl={AccountController.create.url()}
        icon="ph--wallet-bold"
        label="No accounts yet" />
{:else}
    <ToggleableCard class="-mx-2.5" title="Accounts" bind:mode>
        <div class={cn('grid', mode === 'list' ? 'grid-cols-1 gap-4' : 'grid-cols-2 gap-3')}>
            {#each accounts as account (account.id)}
                <Link href={AccountController.show.url({ account: account.id })}>
                    <AccountCard {account} />
                </Link>
            {/each}
        </div>
    </ToggleableCard>
{/if}
