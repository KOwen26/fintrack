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
        [AccountType.DebitAccount]: {
            label: 'Debit',
            color: 'primary',
            icon: 'solar--banknote-2-bold-duotone',
        },
        [AccountType.CreditCard]: {
            label: 'Credit Card',
            color: 'warning',
            icon: 'solar--card-bold-duotone',
        },
        [AccountType.CashWallet]: {
            label: 'Cash',
            color: 'success',
            icon: 'solar--wallet-bold-duotone',
        },
        [AccountType.EWallet]: {
            label: 'E-Wallet',
            color: 'info',
            icon: 'solar--smartphone-bold-duotone',
        },
        [AccountType.Investment]: {
            label: 'Investment',
            color: 'secondary',
            icon: 'solar--graph-bold-duotone',
        },
    };

    const badge = $derived(config[type]);
</script>

<Badge
    class={cn(['gap-1 px-2.5', icon === 'only' && 'size-6 px-0.5', _class])}
    color={badge.color}
    variant="solid">
    <i class="iconify size-4 {badge.icon} {icon === 'hide' ? 'hidden' : ''}"></i>
    <span data-slot="badge-label" class={icon === 'only' ? 'hidden' : ''}>{badge.label}</span>
</Badge>
