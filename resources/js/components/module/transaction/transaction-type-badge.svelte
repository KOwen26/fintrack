<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionType } = $props();

    const config: Record<App.Enums.TransactionType, { label: string; color: ColorVariant }> = {
        [TransactionType.Income]: { label: 'Income', color: 'success' },
        [TransactionType.Expense]: { label: 'Expense', color: 'error' },
        [TransactionType.TransferOut]: { label: 'Transfer Out', color: 'warning' },
        [TransactionType.TransferIn]: { label: 'Transfer In', color: 'info' },
        [TransactionType.Fee]: { label: 'Fee', color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
