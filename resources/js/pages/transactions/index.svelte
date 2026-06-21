<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionCard from '@components/module/transaction/transaction-card.svelte';
    import Button from '@components/ui/button.svelte';

    interface PaginatedTransactions {
        data: App.Models.Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        // account,
        transactions,
        // balance,
    }: {
        // account: App.Models.Account;
        transactions: PaginatedTransactions;
        // balance: string;
    } = $props();
</script>

<div class="">
    <!-- Header -->
    <!-- <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountController.show.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">{account.name}</h1>
            <p class="text-xs text-base-content/50">Transactions</p>
        </div>
        <Button color="primary" href={TransactionController.create.url()} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div> -->

    <!-- Balance card -->
    <!-- <Card wrapperClass="mb-4 bg-primary text-primary-content">
        <p class="text-xs opacity-70">Current Balance</p>
        <p class="font-mono text-2xl font-bold">{account.currency} {formattedBalance}</p>
    </Card> -->

    <!-- Transaction list -->
    {#if transactions.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/40">
            <i class="iconify mb-3 size-12 ph--receipt-bold"></i>
            <p class="text-sm">No transactions yet</p>
            <Button
                class="mt-4"
                color="primary"
                href={TransactionController.create.url()}
                size="sm">
                Add your first transaction
            </Button>
        </div>
    {:else}
        <div class="space-y-2">
            {#each transactions.data as transaction (transaction.id)}
                <TransactionCard {transaction} />
            {/each}
        </div>

        <!-- Pagination -->
        {#if transactions.last_page > 1}
            <div class=" flex items-center justify-center gap-1 flex-wrap">
                {#each transactions.links as link (link.label)}
                    {#if link.url}
                        <Link
                            class="btn btn-xs {link.active ? 'btn-primary' : 'btn-ghost'}"
                            href={link.url}
                            preserveScroll>
                            {link.label}
                        </Link>
                    {:else}
                        <span class="btn btn-xs btn-disabled">{link.label}</span>
                    {/if}
                {/each}
            </div>
        {/if}
    {/if}
</div>
