<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AlertLevel from '@wayfinder/App/Enums/AlertLevel';

    import Badge from '@components/ui/badge.svelte';

    let { level }: { level: App.Enums.AlertLevel } = $props();

    const config: Record<App.Enums.AlertLevel, { label: string; color: ColorVariant }> = {
        [AlertLevel.Normal]: { label: 'Normal', color: 'success' },
        [AlertLevel.Warning]: { label: 'Warning', color: 'warning' },
        [AlertLevel.HighRisk]: { label: 'High Risk', color: 'error' },
    };

    const badge = $derived(config[level]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
