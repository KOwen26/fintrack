<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';

    /* ── Type helpers ────────────────────────────────────── */

    export const TYPE_STYLE: Record<
        App.Enums.TransactionType,
        { label: string; color: string; bg: string; icon: string; signIcon: string }
    > = {
        [TransactionType.Income]: {
            label: 'Pemasukan',
            color: 'text-success',
            bg: 'bg-success/12',
            icon: 'solar--arrow-up-line-duotone',
            signIcon: 'solar--add-bold-duotone',
        },
        [TransactionType.Expense]: {
            label: 'Pengeluaran',
            color: 'text-error',
            bg: 'bg-error/12',
            icon: 'solar--arrow-down-line-duotone',
            signIcon: 'solar--minus-bold-duotone',
        },
        [TransactionType.TransferOut]: {
            label: 'Transfer Keluar',
            color: 'text-warning',
            bg: 'bg-warning/12',
            icon: 'solar--transfer-horizontal-bold-duotone',
            signIcon: '',
        },
        [TransactionType.TransferIn]: {
            label: 'Transfer Masuk',
            color: 'text-info',
            bg: 'bg-info/12',
            icon: 'solar--arrow-up-line-duotone',
            signIcon: 'solar--add-bold-duotone',
        },
        [TransactionType.Fee]: {
            label: 'Biaya',
            color: 'text-secondary',
            bg: 'bg-secondary/12',
            icon: 'solar--transfer-horizontal-bold-duotone',
            signIcon: 'solar--minus-bold-duotone',
        },
    };
</script>

<script lang="ts">
    import type { RestProps } from '@type/index';

    import { Link } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transaction: App.Models.Transaction;
        class?: string;
    }

    let { transaction, class: _class }: Props = $props();

    const typeConfig = $derived(TYPE_STYLE[transaction.type]);
</script>

<Link
    class={cn(
        'flex items-center gap-3 border-b border-base-content/10 px-4 py-3 transition-colors duration-100 last:border-b-0 hover:bg-base-200/40',
        _class
    )}
    href={TransactionController.show.url(transaction)}>
    <div class={cn('flex size-10 shrink-0 items-center justify-center rounded-xl', typeConfig.bg)}>
        <i class={cn('iconify size-4', typeConfig.icon, typeConfig.color)}></i>
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
        <div class={cn('font-mono text-sm font-medium', typeConfig.color)}>
            {#if typeConfig.signIcon}
                <i
                    class={cn(
                        'iconify inline-block size-3.5',
                        typeConfig.signIcon,
                        typeConfig.color
                    )}></i>
            {/if}
            {Formatter.currency(transaction.amount)}
        </div>
    </div>
</Link>
