<script lang="ts">
    import type { Data } from '@type/type';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import AccountInfo from '@components/module/account/account-info.svelte';
    import CategoryInfo from '@components/module/category/category-info.svelte';
    import TransactionTypeBadge from '@components/module/transaction/transaction-type-badge.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    interface Props {
        transaction: Data.Transaction.TransactionDetailData;
    }

    let { transaction }: Props = $props();

    const isInflow = $derived(['income', 'transfer_in'].includes(transaction.type));

    const isTransfer = $derived(
        transaction.type === 'transfer_out' || transaction.type === 'transfer_in'
    );

    const accentClass = $derived(isInflow ? 'bg-success' : isTransfer ? 'bg-warning' : 'bg-error');

    const amountColor = $derived(
        isInflow ? 'text-success' : isTransfer ? 'text-info' : 'text-error'
    );

    const createdAt = $derived(DateTimeHelper.format(transaction.created_at, 'datetime'));
    const updatedAt = $derived(DateTimeHelper.format(transaction.updated_at, 'datetime'));
</script>

<div class="space-y-5">
    <ResponsiveCard class="overflow-x-clip">
        <!-- Colour-coded accent bar — the page's signature element -->
        <div class="-mx-5 h-1 md:-mx-6 {accentClass}"></div>

        <div class="space-y-5 pt-5 md:p-0">
            <!-- Type badge row -->
            <div>
                <TransactionTypeBadge type={transaction.type} />
            </div>

            <!-- Amount -->
            <div class="flex items-start gap-1.5">
                <span
                    class="font-mono text-xl leading-none font-semibold tracking-tight text-base-content/50">
                    Rp
                </span>
                <span
                    class="font-mono text-5xl leading-none font-semibold tracking-tight {amountColor}">
                    {Formatter.currency(transaction.amount, true)}
                </span>
            </div>

            <!-- Description (payee/merchant name) + date -->
            {#if transaction.description}
                <p class="text-base font-semibold text-base-content">{transaction.description}</p>
            {/if}

            <div class="flex items-center gap-2 text-sm text-base-content/70">
                <span class="flex items-center gap-1">
                    <i class="iconify size-5 solar--calendar-bold-duotone"></i>
                    {DateTimeHelper.format(transaction.transaction_date, 'date')}
                </span>
                <span class="size-1 rounded-full bg-base-content/30"></span>
                <span class="flex items-center gap-1">
                    <i class="iconify size-5 solar--clock-circle-bold-duotone"></i>
                    {DateTimeHelper.format(transaction.transaction_date, 'time')}
                </span>
            </div>
        </div>
    </ResponsiveCard>

    <ResponsiveCard>
        <div class="grid grid-cols-2 gap-5">
            <div class={['text-left', !isTransfer ? 'col-span-full' : '']}>
                <h4
                    class="mb-1.5 text-sm font-semibold tracking-widest text-base-content/50 uppercase">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </h4>

                <AccountInfo account={transaction.account} />
            </div>

            <div class={['text-right', !isTransfer ? 'hidden' : '']}>
                <h4
                    class="mb-1.5 text-sm font-semibold tracking-widest text-base-content/50 uppercase">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </h4>

                <AccountInfo account={transaction.account} reverse />
            </div>
        </div>
    </ResponsiveCard>

    <ResponsiveCard>
        <h4 class="mb-1.5 text-sm font-semibold tracking-widest text-base-content/50 uppercase">
            Category
        </h4>

        <CategoryInfo category={transaction.category} />
    </ResponsiveCard>

    <ResponsiveCard>
        <h4 class="mb-1.5 text-sm font-semibold tracking-widest text-base-content/50 uppercase">
            Details
        </h4>

        <div class="space-y-3">
            <!-- Notes -->
            {#if transaction.description}
                <div class="flex items-start gap-3">
                    <i
                        class="mt-0.5 iconify size-5 shrink text-base-content/50 solar--document-text-bold-duotone"
                    ></i>
                    <div>
                        <p class="mb-0.5 text-sm text-base-content/50">Notes</p>
                        <p class="text-sm leading-relaxed font-medium text-pretty">
                            {transaction.description}
                        </p>
                    </div>
                </div>

                <hr class="border-base-content/25" />
            {/if}
            <!-- Created / Updated -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <i
                        class="mt-0.5 iconify size-5 shrink text-base-content/50 solar--clock-circle-bold-duotone"
                    ></i>
                    <div>
                        <p class="mb-0.5 text-sm text-base-content/50">Created</p>
                        <p class="text-sm font-medium">{createdAt}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="mb-0.5 text-sm text-base-content/50">Updated</p>
                    <p class="text-sm font-medium">{updatedAt}</p>
                </div>
            </div>
        </div>
    </ResponsiveCard>

    <!-- ════════════════════════════════════════════ -->
    <!--  CREATOR INFO (if available)                -->
    <!-- ════════════════════════════════════════════ -->
    {#if transaction?.creator}
        <ResponsiveCard>
            <h4 class="mb-1.5 text-sm font-semibold tracking-widest text-base-content/50 uppercase">
                Created by
            </h4>

            <div class="flex items-center gap-3">
                <div
                    class="flex size-10 shrink items-center justify-center rounded-xl bg-accent/10">
                    <i class="iconify size-5 text-accent solar--user-bold-duotone"></i>
                </div>
                <div>
                    <p class="font-semibold">
                        {transaction.creator.name}
                    </p>
                </div>
            </div>
        </ResponsiveCard>
    {/if}
</div>
