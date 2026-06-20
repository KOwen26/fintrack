<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface PaginatedTransactions {
        data: App.Models.Transaction[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
    }

    let {
        account,
        transactions,
        balance,
    }: {
        account: App.Models.Account;
        transactions: PaginatedTransactions;
        balance: string;
    } = $props();

    const formattedBalance = $derived(
        Number(balance).toLocaleString('id-ID', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })
    );
</script>

<div class="p-4">
    <!-- Header -->
    <div class="mb-4 flex items-center gap-3">
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
    </div>

    <!-- Balance card -->
    <Card wrapperClass="mb-4 bg-primary text-primary-content">
        <p class="text-xs opacity-70">Current Balance</p>
        <p class="font-mono text-2xl font-bold">{account.currency} {formattedBalance}</p>
    </Card>

    <!-- Transaction list -->
    {#if transactions.data.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/40">
            <i class="iconify mb-3 size-12 ph--receipt-bold"></i>
            <p class="text-sm">No transactions yet</p>
            <Button
                class="mt-4"
                color="primary"
                href={TransactionController.create.url({ account: account.id })}
                size="sm">
                Add your first transaction
            </Button>
        </div>
    {:else}
        <div class="space-y-2">
            {#each transactions.data as transaction (transaction.id)}
                <a
                    class="block"
                    href={TransactionController.edit.url({
                        account: account.id,
                        transaction: transaction.id,
                    })}>
                    <Card wrapperClass="transition-transform active:scale-95">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="truncate text-sm font-medium">
                                        {transaction.description ??
                                            transaction.category?.name ??
                                            'Transaction'}
                                    </p>
                                    <div class="mt-1 flex items-center gap-1">
                                        <TransactionTypeBadge type={transaction.type} />
                                        {#if transaction.category}
                                            <span class="text-xs text-base-content/50"
                                                >{transaction.category.name}</span>
                                        {/if}
                                    </div>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p
                                    class="font-mono text-sm font-semibold {[
                                        'income',
                                        'transfer_in',
                                    ].includes(transaction.type)
                                        ? 'text-success'
                                        : 'text-error'}">
                                    {['income', 'transfer_in'].includes(transaction.type)
                                        ? '+'
                                        : '-'}{Number(transaction.amount).toLocaleString('id-ID', {
                                        minimumFractionDigits: 0,
                                        maximumFractionDigits: 0,
                                    })}
                                </p>
                                <p class="text-xs text-base-content/40">
                                    {new Date(
                                        transaction.transaction_date as string
                                    ).toLocaleDateString('id-ID', {
                                        day: '2-digit',
                                        month: 'short',
                                    })}
                                </p>
                            </div>
                        </div>
                    </Card>
                </a>
            {/each}
        </div>

        <!-- Pagination -->
        {#if transactions.last_page > 1}
            <div class="mt-6 flex items-center justify-center gap-1 flex-wrap">
                {#each transactions.links as link (link.label)}
                    {#if link.url}
                        <Link
                            class="btn btn-xs {link.active ? 'btn-primary' : 'btn-ghost'}"
                            href={link.url}
                            preserveScroll>
                            {@html link.label}
                        </Link>
                    {:else}
                        <span class="btn btn-xs btn-disabled">{@html link.label}</span>
                    {/if}
                {/each}
            </div>
        {/if}
    {/if}
</div>
