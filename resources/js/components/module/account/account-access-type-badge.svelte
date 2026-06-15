<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountAccessType } = $props();

    const config: Record<App.Enums.AccountAccessType, { label: string; color: ColorVariant }> = {
        [AccountAccessType.Personal]: { label: 'Personal', color: 'light' },
        [AccountAccessType.Joint]: { label: 'Joint', color: 'accent' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
