<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import ProviderType from '@wayfinder/App/Enums/ProviderType';

    import Badge from '@components/ui/badge.svelte';

    let { provider, ...props }: { provider: App.Models.Provider | null } = $props();

    const config: Record<App.Enums.ProviderType, { label: string; color: ColorVariant }> = {
        [ProviderType.Bank]: { label: 'Bank', color: 'primary' },
        [ProviderType.DigitalBank]: { label: 'Digital Bank', color: 'info' },
        [ProviderType.EWallet]: { label: 'E-Wallet', color: 'success' },
        [ProviderType.CreditLoan]: { label: 'Credit / Loan', color: 'warning' },
        [ProviderType.Investment]: { label: 'Investment', color: 'secondary' },
    };

    const badge = $derived(provider ? config[provider.type] : null);
</script>

<Badge color={badge?.color ?? 'light'} variant="solid" {...props}>
    {provider?.name ?? 'Unknown Provider'}</Badge>
