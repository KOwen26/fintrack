<script lang="ts">
    import type { ColorVariant } from '@/data/theme';

    import Badge from '@components/ui/badge.svelte';

    type BudgetStatus = 'on_track' | 'at_risk' | 'over_budget';

    let { status }: { status: BudgetStatus } = $props();

    const config: Record<BudgetStatus, { label: string; color: ColorVariant }> = {
        on_track: { label: 'On Track', color: 'success' },
        at_risk: { label: 'At Risk', color: 'warning' },
        over_budget: { label: 'Over Budget', color: 'error' },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
