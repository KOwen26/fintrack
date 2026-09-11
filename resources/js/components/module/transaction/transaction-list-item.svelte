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
            icon: 'solar--transfer-horizontal-bold-duotone',
        },
    };
</script>

<script lang="ts">
    import type { TransactionListData } from '@type/generated';
    import type { RestProps } from '@type/index';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import IconBadge from '@components/ui/icon-badge.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transaction: TransactionListData;
        class?: string;
        hideIcon?: boolean;
    }

    let { transaction, hideIcon = false, class: _class }: Props = $props();

    const typeConfig = $derived(TYPE_STYLE[transaction.type]);

    const signIcon = $derived(
        transaction.flow === 'inflow' ? 'solar--add-bold-duotone' : 'solar--minus-bold-duotone'
    );

    /* Category decoration drives the tile; kind styling is the fallback
       (transfers and uncategorized rows keep the kind-colored tile). */
    const decoColor = $derived(
        transaction.category?.decorations?.color
            ? (getDecorationColor(transaction.category.decorations.color)?.hex ?? undefined)
            : undefined
    );

    const decoIcon = $derived(
        transaction.category?.decorations?.icon
            ? (getDecorationIcon(transaction.category.decorations.icon)?.value ??
                  'solar--tag-bold-duotone')
            : undefined
    );

    /* Resolved tile config — passed to <IconBadge>. */
    const tileIcon = $derived(decoIcon ?? typeConfig.icon);
    const tileBackground = $derived(decoColor ? `${decoColor}20` : typeConfig.bg);
    const tileColor = $derived(decoColor ?? typeConfig.color);
</script>

<Link
    class={cn(
        'flex items-center gap-2 border-b border-base-content/10 p-3 transition-colors duration-100 last:border-b-0 hover:bg-base-200/40',
        _class
    )}
    href={TransactionController.show.url(transaction)}>
    <!-- Icon -->
    {#if !hideIcon}
        <IconBadge background={tileBackground} color={tileColor} icon={tileIcon} size="sm" />
    {/if}

    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-medium text-base-content">
            {transaction.description}
        </div>
        <div class="mt-0.5 flex items-center gap-1.5 text-xs">
            {#if transaction.category}
                <span class="truncate">{transaction.category.name}</span>
            {/if}
            {#if transaction.category && transaction.account}
                <span class="size-0.5 shrink-0 rounded-full bg-base-content/20"></span>
            {/if}
            {#if transaction.account}
                <span class="truncate">{transaction.account.name}</span>
            {/if}
        </div>
    </div>

    <div class="shrink-0 text-right">
        <div style:color={typeConfig.color} class="font-mono text-sm font-semibold">
            {Formatter.currency(transaction.amount)}
        </div>
    </div>
</Link>
