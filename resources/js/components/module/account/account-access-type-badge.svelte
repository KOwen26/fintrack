<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';

    import { cn } from '@utilities/shadcn';

    import Badge from '@components/ui/badge.svelte';

    interface Props {
        type: App.Enums.AccountAccessType;
        icon?: 'show' | 'hide' | 'only';
    }

    let { type, icon = 'show' }: Props = $props();

    const config: Record<
        App.Enums.AccountAccessType,
        { label: string; color: ColorVariant; icon: string }
    > = {
        [AccountAccessType.Personal]: { label: 'Personal', color: 'light', icon: 'ph--user-bold' },
        [AccountAccessType.Joint]: {
            label: 'Joint',
            color: 'accent',
            icon: 'ph--users-three-bold',
        },
    };

    const badge = $derived(config[type]);
</script>

<Badge
    class={cn(['gap-1 px-2.5', icon === 'only' && 'px-0.5 size-6'])}
    color={badge.color}
    variant="soft">
    <i class="iconify size-3 {badge.icon} {icon === 'hide' ? 'hidden' : ''}"></i>

    <span class={icon === 'only' ? 'hidden' : ''}>{badge.label}</span>
</Badge>
