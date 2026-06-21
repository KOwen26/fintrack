<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { cn } from '@utilities/shadcn';

    interface Props {
        account: App.Models.Account;
        labelOnly?: boolean;
    }

    let { account, labelOnly = false }: Props = $props();

    const color = $derived(account.decorations?.color);
</script>

<span
    data-slot="account-badge"
    style:--bg-color={labelOnly ? 'transparent' : color?.value}
    style:--text-color={labelOnly
        ? `color-mix(in oklab, ${color?.value} 100%, #000 40%)`
        : (color?.text_color ?? '#FFFFFF')}
    class={cn(
        'font-semibold ',
        labelOnly ? '' : 'badge rounded border-none px-2',
        'bg-(--bg-color) text-(--text-color)'
    )}>
    {account.name}
</span>
