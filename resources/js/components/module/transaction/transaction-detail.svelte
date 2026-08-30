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
        <div class="-mx-5 md:-mx-6 h-1 {accentClass}"></div>

        <div class="pt-5 md:p-0 space-y-5">
            <!-- Type badge row -->
            <div>
                <TransactionTypeBadge type={transaction.type} />
            </div>

            <!-- Amount -->
            <div class="flex items-start gap-1.5">
                <span
                    class="font-mono text-xl font-semibold tracking-tight text-base-content/50 leading-none">
                    Rp
                </span>
                <span
                    class="font-mono text-5xl font-semibold tracking-tight leading-none {amountColor}">
                    {Formatter.currency(transaction.amount, true)}
                </span>
            </div>

            <!-- Description (payee/merchant name) + date -->
            {#if transaction.description}
                <p class="font-semibold text-base text-base-content">{transaction.description}</p>
            {/if}

            <div class="flex items-center gap-2 text-sm text-base-content/70">
                <span class="flex items-center gap-1">
                    <i class="iconify size-5 ph--calendar-blank-bold"></i>
                    {DateTimeHelper.format(transaction.transaction_date, 'date')}
                </span>
                <span class="rounded-full size-1 bg-base-content/30"></span>
                <span class="flex items-center gap-1">
                    <i class="iconify size-5 ph--clock-bold"></i>
                    {DateTimeHelper.format(transaction.transaction_date, 'time')}
                </span>
            </div>
        </div>
    </ResponsiveCard>

    <ResponsiveCard>
        <div class="grid grid-cols-2 gap-5">
            <div class={['text-left', !isTransfer ? 'col-span-full' : '']}>
                <h4
                    class="text-sm mb-1.5 font-semibold tracking-widest uppercase text-base-content/50">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </h4>

                <AccountInfo account={transaction.account} />
            </div>

            <div class={['text-right', !isTransfer ? 'hidden' : '']}>
                <h4
                    class="text-sm mb-1.5 font-semibold tracking-widest uppercase text-base-content/50">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </h4>

                <AccountInfo account={transaction.account} reverse />
            </div>
        </div>
    </ResponsiveCard>

    <ResponsiveCard>
        <h4 class="text-sm mb-1.5 font-semibold tracking-widest uppercase text-base-content/50">
            Category
        </h4>

        <CategoryInfo category={transaction.category} />
    </ResponsiveCard>

    <ResponsiveCard>
        <h4 class="text-sm mb-1.5 font-semibold tracking-widest uppercase text-base-content/50">
            Details
        </h4>

        <div class="space-y-3">
            <!-- Notes -->
            {#if transaction.description}
                <div class="flex items-start gap-3">
                    <i class="iconify size-5 text-base-content/50 shrink mt-0.5 ph--note-bold"></i>
                    <div>
                        <p class="text-sm text-base-content/50 mb-0.5">Notes</p>
                        <p class="text-sm font-medium leading-relaxed text-pretty">
                            {transaction.description}
                        </p>
                    </div>
                </div>

                <hr class="border-base-content/25" />
            {/if}
            <!-- Created / Updated -->
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <i class="iconify size-5 text-base-content/50 shrink mt-0.5 ph--clock-bold"></i>
                    <div>
                        <p class="text-sm text-base-content/50 mb-0.5">Created</p>
                        <p class="text-sm font-medium">{createdAt}</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm text-base-content/50 mb-0.5">Updated</p>
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
            <h4 class="text-sm mb-1.5 font-semibold tracking-widest uppercase text-base-content/50">
                Created by
            </h4>

            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-xl bg-accent/10 flex items-center justify-center shrink">
                    <i class="iconify size-5 text-accent ph--user-bold"></i>
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
