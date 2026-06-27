<script lang="ts">
    import type { Data } from '@type/type';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import AccountBadge from '@components/module/account/account-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
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

    let txIdCopied = $state(false);

    function copyTransactionId(): void {
        const id = String(transaction.id);
        navigator.clipboard?.writeText(id).then(() => {
            txIdCopied = true;
            setTimeout(() => (txIdCopied = false), 1600);
        });
    }
</script>

<div class="space-y-5">
    <!-- ════════════════════════════════════════════ -->
    <!--  HERO CARD                                  -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="overflow-x-clip">
        <!-- Colour-coded accent bar — the page's signature element -->
        <div class="-mx-5 md:-mx-6 h-1 {accentClass}"></div>

        <div class="p-2.5 pt-5 md:p-0 space-y-5">
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

    <!-- ════════════════════════════════════════════ -->
    <!--  ACCOUNT SOURCE CARD                        -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="p-2.5" contentClass="space-y-3">
        <div class="grid grid-cols-2 gap-5">
            <div class={['text-left', !isTransfer ? 'col-span-full' : '']}>
                <p class=" font-semibold tracking-widest uppercase text-base-content/50">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </p>

                <div class="flex items-center gap-3">
                    <div
                        class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i class="iconify size-5 text-primary ph--bank-bold"></i>
                    </div>
                    <div class="grow">
                        <AccountBadge account={transaction.account} labelOnly />
                        <p class="my-1"></p>
                        <AccountTypeBadge type={transaction.account?.type} />
                    </div>
                </div>
            </div>

            <div class={['text-right', !isTransfer ? 'hidden' : '']}>
                <p class=" font-semibold tracking-widest uppercase text-base-content/50">
                    {isInflow ? 'Destination Account' : 'Source Account'}
                </p>

                <div class="flex flex-row-reverse items-center gap-3">
                    <div
                        class="size-10 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                        <i class="iconify size-5 text-primary ph--bank-bold"></i>
                    </div>
                    <div class="grow">
                        <AccountBadge account={transaction.account} labelOnly />
                        <p class="my-1"></p>
                        <AccountTypeBadge type={transaction.account?.type} />
                    </div>
                </div>
            </div>
        </div>
    </ResponsiveCard>

    <!-- ════════════════════════════════════════════ -->
    <!--  CATEGORY CARD                              -->
    <!-- ════════════════════════════════════════════ -->
    {#if transaction?.category}
        <ResponsiveCard class="p-2.5" contentClass="space-y-5">
            <p class=" font-semibold tracking-widest uppercase text-base-content/50">Category</p>

            <div class="flex items-center gap-3">
                <div
                    style:background={transaction.category.decorations?.color?.value
                        ? `${transaction.category.decorations.color.value}20`
                        : undefined}
                    style:color={transaction.category.decorations?.color?.value ?? undefined}
                    class="size-10 rounded-xl bg-base-content/10 flex items-center justify-center shrink-0 text-lg">
                    {#if transaction.category.decorations?.icon}
                        <i class="iconify size-5 {transaction.category.decorations.icon}"></i>
                    {:else}
                        <i class="iconify size-5 ph--tag-bold"></i>
                    {/if}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-sm text-base-content">
                        {transaction.category.name}
                    </p>
                </div>
                <i class="iconify size-5 text-base-content/20 ph--caret-right-bold"></i>
            </div>
        </ResponsiveCard>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  DETAIL FIELDS CARD                         -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="p-2.5" contentClass="space-y-5">
        <p class=" font-semibold tracking-widest uppercase text-base-content/50">Details</p>

        <!-- Notes -->
        {#if transaction.description}
            <div class="flex items-start gap-3">
                <i class="iconify size-5 text-base-content/50 shrink-0 mt-0.5 ph--note-bold"></i>
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Notes</p>
                    <p class="text-sm text-base-content leading-relaxed text-pretty">
                        {transaction.description}
                    </p>
                </div>
            </div>

            <hr class="border-base-content/10" />
        {/if}

        <!-- Transaction ID -->
        <!-- <div class="flex items-center gap-3">
            <i class="iconify size-5 text-base-content/50 shrink-0 ph--identification-badge-bold"
            ></i>
            <div class="flex-1 min-w-0">
                <p class="text-xs text-base-content/50 mb-0.5">Transaction ID</p>
                <p class="font-mono text-sm text-base-content">#{transaction.id}</p>
            </div>
            <button
                onclick={copyTransactionId}
                class="btn btn-ghost btn-xs text-base-content/60 hover:text-base-content"
                aria-label="Copy transaction ID">
                {#if txIdCopied}
                    <i class="iconify size-5 ph--check-bold text-success"></i>
                {:else}
                    <i class="iconify size-5 ph--copy-bold"></i>
                {/if}
            </button>
        </div>

        <hr class="border-base-content/10" /> -->

        <!-- Created / Updated -->
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <i class="iconify size-5 text-base-content/50 shrink-0 mt-0.5 ph--clock-bold"></i>
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Created</p>
                    <p class="text-sm text-base-content">{createdAt}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-base-content/50 mb-0.5">Updated</p>
                <p class="text-sm text-base-content">{updatedAt}</p>
            </div>
        </div>
    </ResponsiveCard>

    <!-- ════════════════════════════════════════════ -->
    <!--  CREATOR INFO (if available)                -->
    <!-- ════════════════════════════════════════════ -->
    {#if transaction?.creator}
        <ResponsiveCard class="p-2.5" contentClass=" space-y-3">
            <p class=" font-semibold tracking-widest uppercase text-base-content/50">Created by</p>
            <div class="flex items-center gap-3">
                <div
                    class="size-10 rounded-xl bg-accent/10 flex items-center justify-center shrink-0">
                    <i class="iconify size-5 text-accent ph--user-bold"></i>
                </div>
                <div>
                    <p class="font-semibold text-base text-base-content">
                        {transaction.creator.name}
                    </p>
                </div>
            </div>
        </ResponsiveCard>
    {/if}
</div>
