<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.TransactionPresetType } = $props();

    const config: Record<App.Enums.TransactionPresetType, { label: string; color: ColorVariant }> =
        {
            [TransactionPresetType.Income]: { label: 'Income', color: 'success' },
            [TransactionPresetType.Expense]: { label: 'Expense', color: 'error' },
            [TransactionPresetType.Transfer]: { label: 'Transfer', color: 'info' },
        };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
