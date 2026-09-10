<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { TransactionKind } from '@schema/transaction.schema';
    import type { App } from '@wayfinder/types';

    import { resolveKind } from '@schema/transaction.schema';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionType } = $props();

    const config: Record<TransactionKind, { label: string; color: ColorVariant }> = {
        income: { label: 'Income', color: 'success' },
        expense: { label: 'Expense', color: 'error' },
        transfer: { label: 'Transfer', color: 'info' },
    };

    const badge = $derived(config[resolveKind(type)]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
