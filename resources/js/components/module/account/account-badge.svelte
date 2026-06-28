<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';

    import { cn } from '@utilities/shadcn';

    interface Props {
        account: App.Models.Account;
        labelOnly?: boolean;
        class?: string;
    }

    let { account, labelOnly = false, class: _class }: Props = $props();

    const colorObj = $derived(
        account.decorations?.color ? getDecorationColor(account.decorations.color) : undefined
    );
</script>

<span
    data-slot="account-badge"
    style:--bg-color={labelOnly ? 'transparent' : colorObj?.value}
    style:--text-color={labelOnly
        ? `color-mix(in oklab, ${colorObj?.value} 100%, #000 40%)`
        : (colorObj?.text_color ?? '#FFFFFF')}
    class={cn(
        'font-semibold ',
        labelOnly ? '' : 'badge rounded border-none px-2',
        'bg-(--bg-color) text-(--text-color)',
        _class
    )}>
    {account.name}
</span>
