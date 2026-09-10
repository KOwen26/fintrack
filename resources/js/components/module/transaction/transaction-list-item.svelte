<script lang="ts" module>
    import type { TransactionKind } from '@schema/transaction.schema';

    /* ── Kind style map ──────────────────────────────────── */

    export const TYPE_STYLE: Record<
        TransactionKind,
        { label: string; color: string; bg: string; icon: string; signIcon: string }
    > = {
        income: {
            label: 'Income',
            color: 'var(--color-success)',
            bg: 'color-mix(in oklab, var(--color-success) 12%, transparent)',
            icon: 'solar--arrow-up-line-duotone',
            signIcon: 'solar--add-bold-duotone',
        },
        expense: {
            label: 'Expense',
            color: 'var(--color-error)',
            bg: 'color-mix(in oklab, var(--color-error) 12%, transparent)',
            icon: 'solar--arrow-down-line-duotone',
            signIcon: 'solar--minus-bold-duotone',
        },
        transfer: {
            label: 'Transfer',
            color: 'var(--color-info)',
            bg: 'color-mix(in oklab, var(--color-info) 12%, transparent)',
            icon: 'solar--transfer-horizontal-bold-duotone',
            signIcon: '',
        },
    };
</script>

<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Data } from '@type/type';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { resolveKind } from '@schema/transaction.schema';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transaction: Data.TransactionListData;
        class?: string;
    }

    let { transaction, class: _class }: Props = $props();

    const typeConfig = $derived(TYPE_STYLE[resolveKind(transaction.type)]);
</script>

<Link
    class={cn(
        'flex items-center gap-3 border-b border-base-content/10 px-4 py-3 transition-colors duration-100 last:border-b-0 hover:bg-base-200/40',
        _class
    )}
    href={TransactionController.show.url(transaction)}>
    <div
        class="flex size-10 shrink-0 items-center justify-center rounded-xl"
        style:background={typeConfig.bg}>
        <i class="iconify size-4 {typeConfig.icon}" style:color={typeConfig.color}></i>
    </div>
    <div class="min-w-0 flex-1">
        <div class="truncate text-sm font-semibold text-base-content">
            {transaction.description}
        </div>
        <div class="mt-0.5 flex items-center gap-1.5 text-xs text-base-content/40">
            <span>{transaction?.category?.name}</span>
            {#if transaction.account}
                <span class="size-0.5 rounded-full bg-base-content/20"></span>
                <span>{transaction.account.name}</span>
            {/if}
        </div>
    </div>
    <div class="shrink-0 text-right">
        <div class="font-mono text-sm font-medium" style:color={typeConfig.color}>
            {#if typeConfig.signIcon}
                <i
                    class="iconify inline-block size-3.5 {typeConfig.signIcon}"
                    style:color={typeConfig.color}></i>
            {/if}
            {Formatter.currency(transaction.amount)}
        </div>
    </div>
</Link>
