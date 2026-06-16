<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';

    import Badge from '@components/ui/badge.svelte';

    let { frequency }: { frequency: App.Enums.RecurringFrequency } = $props();

    const config: Record<App.Enums.RecurringFrequency, { label: string; color: ColorVariant }> = {
        [RecurringFrequency.Daily]: { label: 'Daily', color: 'warning' },
        [RecurringFrequency.Weekly]: { label: 'Weekly', color: 'info' },
        [RecurringFrequency.Fortnightly]: { label: 'Fortnightly', color: 'secondary' },
        [RecurringFrequency.Monthly]: { label: 'Monthly', color: 'primary' },
        [RecurringFrequency.Yearly]: { label: 'Yearly', color: 'accent' },
    };

    const badge = $derived(config[frequency]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
