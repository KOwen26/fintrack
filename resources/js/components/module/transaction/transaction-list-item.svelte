<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';

    export const TYPE_STYLE: Record<
        App.Enums.TransactionType,
        { label: string; color: string; bg: string; icon: string }
    > = {
        [TransactionType.Income]: {
            label: 'Income',
            color: 'var(--color-success)',
            bg: 'color-mix(in oklab, var(--color-success) 12%, transparent)',
            icon: 'solar--arrow-up-line-duotone',
        },
        [TransactionType.Expense]: {
            label: 'Expense',
            color: 'var(--color-error)',
            bg: 'color-mix(in oklab, var(--color-error) 12%, transparent)',
            icon: 'solar--arrow-down-line-duotone',
        },
        [TransactionType.Transfer]: {
            label: 'Transfer',
            color: 'var(--color-info)',
            bg: 'color-mix(in oklab, var(--color-info) 12%, transparent)',
            icon: 'solar--sort-horizontal-line-duotone',
        },
    };
</script>

<script lang="ts">
    import type { TransactionListData } from '@type/generated';
    import type { RestProps } from '@type/index';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { cn } from '@utilities/shadcn';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transaction: TransactionListData;
        class?: string;
        hideIcon?: boolean;
    }

    let { transaction, hideIcon = false, class: _class }: Props = $props();

    const typeConfig = $derived(TYPE_STYLE[transaction.type]);

    /* Category decoration (with parent/group fallback) — the badge resolves
       the slugs and wins over the transaction-type styling passed as
       fallback props. */
    const iconSlug = $derived(
        transaction.category?.decorations?.icon ?? transaction.category?.group?.decorations?.icon
    );
    const decorations = $derived({
        icon: iconSlug,
        color:
            transaction.category?.decorations?.color ??
            transaction.category?.group?.decorations?.color,
    });
</script>

<Link
    class={cn(
        'flex items-center gap-2 p-2 transition-colors duration-100  hover:bg-card',
        '-mx-2 rounded-md',
        // 'border-b border-border last:border-b-0',
        _class
    )}
    href={TransactionController.show.url(transaction)}>
    <!-- Icon -->
    {#if !hideIcon}
        <DecorationBadge
            background={typeConfig.bg}
            color={typeConfig.color}
            {decorations}
            icon={iconSlug ? 'solar--tag-bold-duotone' : typeConfig.icon}
            size="sm" />
    {/if}

    <div class="min-w-0 flex-1">
        <div class="flex flex-row items-center justify-between text-sm">
            <div class="truncate font-heading font-medium text-foreground">
                {transaction.description}
            </div>

            <CurrencyAmount
                style="color: {typeConfig.color}"
                class="inline-block text-sm font-semibold"
                value={transaction.amount} />
        </div>

        <div class="flex flex-row items-center justify-between gap-2 text-xs">
            <span class="inline-flex items-center gap-2">
                {transaction?.account?.name}

                {#if transaction.type === 'transfer'}
                    <i style="font-size: 1cap;" class="iconify solar--arrow-right-line-duotone"></i>
                    {transaction.destination_account?.name}
                {/if}
            </span>

            {#if transaction.type !== 'transfer'}
                <span class="truncate">{transaction?.category?.name}</span>
            {/if}
        </div>
    </div>
</Link>
