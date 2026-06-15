<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import HouseholdMemberRole from '@wayfinder/App/Enums/HouseholdMemberRole';

    import Badge from '@components/ui/badge.svelte';

    let { role }: { role: App.Enums.HouseholdMemberRole } = $props();

    const config: Record<App.Enums.HouseholdMemberRole, { label: string; color: ColorVariant }> = {
        [HouseholdMemberRole.Owner]: { label: 'Owner', color: 'primary' },
        [HouseholdMemberRole.Member]: { label: 'Member', color: 'light' },
    };

    const badge = $derived(config[role]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
