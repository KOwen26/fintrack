<script lang="ts">
    import type { App } from '@wayfinder/types';
    import type { ComponentProps } from 'svelte';

    import TransactionCard from './transaction-card.svelte';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import ScrollArea from '@components/ui/scroll-area.svelte';

    interface Props {
        transactions: App.Models.Transaction[];
        cardProps?: ComponentProps<typeof TransactionCard>;
    }

    let { transactions, cardProps }: Props = $props();
</script>

{#if transactions.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Add your first transaction"
        ctaUrl={TransactionController.create.url()}
        icon="ph--receipt-bold"
        label="No transactions yet" />
{:else}
    <ResponsiveCard title="Transactions">
        <ScrollArea class="h-160">
            <div class="grid gap-y-2.5">
                {#each transactions as transaction (transaction.id)}
                    <Link href={TransactionController.show.url(transaction)}>
                        <TransactionCard {transaction} {...cardProps} />
                    </Link>
                {/each}
            </div>
        </ScrollArea>
    </ResponsiveCard>
{/if}
