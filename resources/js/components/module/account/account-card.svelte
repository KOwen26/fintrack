<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import AccountTypeBadge from './account-type-badge.svelte';

    import { getDecorationColor } from '@data/decoration-colors';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        account: App.Models.Account;
    }

    let { account, class: _class }: Props = $props();

    const colorObj = $derived(
        account.decorations?.color ? getDecorationColor(account.decorations.color) : undefined
    );
</script>

<div class="@container/account-card">
    <div
        style:--bg-color={colorObj?.hex}
        style:--text-color={colorObj?.text_color ?? '#FFFFFF'}
        class={cn(
            'p-2.5 rounded @min-[12rem]:rounded-md',
            'bg-(--bg-color) text-(--text-color)',
            'flex h-full flex-col items-start justify-around gap-1.5',
            _class
        )}>
        <div class="flex items-center justify-between w-full">
            <p class="text-sm @min-[12rem]:text-base font-semibold text-current/80 text-nowrap">
                {account.name}
            </p>

            <!-- TODO control label via container query *:data-[slot='badge-label']:hidden -->
            <AccountTypeBadge icon="only" type={account.type} />
        </div>

        <p class="font-semibold text-lg @min-[12rem]:text-xl leading-snug">
            {Formatter.currency(account?.current_balance ?? 100_000_000)}
        </p>
    </div>
</div>
