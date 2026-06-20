<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import TransactionTypeBadge from './transaction-type-badge.svelte';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import AccountBadge from '@components/module/account/account-badge.svelte';
    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        transaction: App.Models.Transaction;
    }

    let { transaction, class: _class }: Props = $props();

    const isInflow = $derived(['income', 'transfer_in'].includes(transaction.type));
    const sign = $derived(isInflow ? '+' : '-');
    const color = $derived(isInflow ? 'text-success' : 'text-error');
</script>

<Card wrapperClass={cn('transition-transform active:scale-95', _class)}>
    <div class="flex items-center justify-between gap-3">
        <div class="flex-1 min-w-0 space-y-1">
            <p class="truncate text-sm font-medium">
                {transaction.description ?? transaction.category?.name ?? 'Transaction'}
            </p>
            <div class="flex items-center gap-1">
                <TransactionTypeBadge type={transaction.type} />
                {#if transaction.account}
                    <AccountBadge account={transaction.account} />
                {/if}
                {#if transaction.category}
                    <span class="text-xs text-base-content/50">{transaction.category.name}</span>
                {/if}
            </div>
        </div>
        <div class="text-right shrink-0">
            <p class="font-mono text-sm font-semibold {color}">
                {sign}
                {Formatter.currency(transaction.amount)}
            </p>
            <p class="text-xs text-base-content/40">
                {DateTimeHelper.format(transaction.transaction_date, 'datetime')}
            </p>
        </div>
    </div>
</Card>
