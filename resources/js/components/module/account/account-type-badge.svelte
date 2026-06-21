<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountType from '@wayfinder/App/Enums/AccountType';

    import { cn } from '@utilities/shadcn';

    import Badge from '@components/ui/badge.svelte';

    interface Props {
        type: App.Enums.AccountType;
        icon?: 'show' | 'hide' | 'only';
        class?: string;
    }

    let { type, icon = 'show', class: _class }: Props = $props();

    const config: Record<
        App.Enums.AccountType,
        { label: string; color: ColorVariant; icon: string }
    > = {
        [AccountType.DebitAccount]: { label: 'Debit', color: 'primary', icon: 'ph--bank-bold' },
        [AccountType.CreditCard]: {
            label: 'Credit Card',
            color: 'warning',
            icon: 'ph--credit-card-bold',
        },
        [AccountType.CashWallet]: { label: 'Cash', color: 'success', icon: 'ph--wallet-bold' },
        [AccountType.EWallet]: {
            label: 'E-Wallet',
            color: 'info',
            icon: 'ph--device-mobile-bold',
        },
        [AccountType.Investment]: {
            label: 'Investment',
            color: 'secondary',
            icon: 'ph--chart-line-bold',
        },
    };

    const badge = $derived(config[type]);
</script>

<Badge
    class={cn(['gap-1 px-2.5', icon === 'only' && 'px-0.5 size-6', _class])}
    color={badge.color}
    variant="solid">
    <i class="iconify size-4 {badge.icon} {icon === 'hide' ? 'hidden' : ''}"></i>
    <span data-slot="badge-label" class={icon === 'only' ? 'hidden' : ''}>{badge.label}</span>
</Badge>
