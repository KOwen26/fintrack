<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import AccountType from '@wayfinder/App/Enums/AccountType';

    import Badge from '@components/ui/badge.svelte';

    let { type }: { type: App.Enums.AccountType } = $props();

    const config: Record<App.Enums.AccountType, { label: string; color: ColorVariant }> = {
        [AccountType.DebitAccount]: { label: 'Debit', color: 'primary' },
        [AccountType.CreditCard]: { label: 'Credit Card', color: 'warning' },
        [AccountType.CashWallet]: { label: 'Cash', color: 'success' },
        [AccountType.EWallet]: { label: 'E-Wallet', color: 'info' },
        [AccountType.Investment]: { label: 'Investment', color: 'secondary' },
    };

    const badge = $derived(config[type]);
</script>

<Badge color={badge.color} variant="soft">{badge.label}</Badge>
