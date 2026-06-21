<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import AccountAccessTypeBadge from './account-access-type-badge.svelte';
    import AccountTypeBadge from './account-type-badge.svelte';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        account: App.Models.Account;
    }

    let { account, class: _class }: Props = $props();

    const className = cn(_class, 'p-4');
</script>

<Card
    wrapperClass={className}
    wrapperProps={{ style: `background-color: ${account?.decorations?.color?.value}` }}>
    <div class="flex items-center justify-between">
        <div class="space-y-1">
            <p class="font-semibold">{account.name}</p>
            <div class="flex items-center gap-1">
                <AccountTypeBadge type={account.type} />
                <AccountAccessTypeBadge type={account.access_type} />
            </div>
        </div>
        <div class="text-right">
            <p class="text-sm text-base-content/70">Current Balance</p>
            <p class="font-semibold">
                {Formatter.currency(account?.current_balance ?? 0)}
            </p>
        </div>
    </div>
</Card>
