<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import CategoryBadge from '../category/category-badge.svelte';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import AccountBadge from '@components/module/account/account-badge.svelte';
    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        transaction: App.Models.Transaction;
        withoutAccount?: boolean;
    }

    let { transaction, withoutAccount = false, class: _class }: Props = $props();

    const isInflow = $derived(['income', 'transfer_in'].includes(transaction.type));
    const color = $derived(isInflow ? 'text-success' : 'text-error');
</script>

<Card class={cn('transition-transform active:scale-95', _class, 'rounded-md p-3')}>
    <div class="grid grid-cols-2 gap-y-2">
        {#if !withoutAccount}
            <div class="col-span-full">
                <AccountBadge account={transaction?.account} labelOnly />
                <hr class="mt-1" />
            </div>
        {/if}
        <div class=" grow space-y-1">
            <div class="flex items-center gap-1.5">
                <CategoryBadge category={transaction?.category} />
            </div>

            <p class="truncate text-sm">
                {transaction.description}
            </p>
        </div>

        <div class=" space-y-1 text-right">
            <p class="text-sm text-base-content/40">
                {DateTimeHelper.format(transaction.transaction_date, 'datetime')}
            </p>

            <p class="flex items-center justify-end gap-1 font-semibold {color}">
                <i
                    class={cn([
                        'iconify size-3 text-current',
                        isInflow ? 'solar--add-bold-duotone' : 'solar--minus-bold-duotone',
                    ])}></i>

                {Formatter.currency(transaction.amount)}
            </p>
        </div>
    </div>

    <!-- <div class="flex items-center justify-between gap-3">
        <div class="grow space-y-1">
            <div class="flex items-center gap-1.5">
                <AccountBadge account={transaction.account} />

                <span class="size-1 rounded-full bg-current"></span>

                <CategoryBadge category={transaction.category} />
            </div>

            <p class="truncate text-sm max-w-3/4">
                {transaction.description}
            </p>
        </div>
        <div class="text-right shrink min-w-fit space-y-1">
            <p class="text-sm text-base-content/40">
                {DateTimeHelper.format(transaction.transaction_date, 'datetime')}
            </p>

            <p class="font-semibold flex items-center gap-1 justify-end {color}">
                <i
                    class={cn([
                        'iconify text-current size-3',
                        isInflow ? 'solar--add-bold-duotone' : 'solar--minus-bold-duotone',
                    ])}></i>

                {Formatter.currency(transaction.amount)}
            </p>
        </div>
    </div> -->
</Card>
