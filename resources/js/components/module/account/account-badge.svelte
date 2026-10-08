<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';

    import { cn } from '@utilities/shadcn';

    import Badge from '@components/ui/badge.svelte';

    interface Props {
        account: App.Models.Account;
        labelOnly?: boolean;
        class?: string;
    }

    let { account, labelOnly = false, class: _class }: Props = $props();

    const colorObj = $derived(
        account.decorations?.color ? getDecorationColor(account.decorations.color) : undefined
    );

    /** Decoration colors travel as CSS custom properties (theme-agnostic oklch/text pair). */
    const bgColor = $derived(labelOnly ? 'transparent' : (colorObj?.oklch ?? 'transparent'));
    const textColor = $derived(
        labelOnly
            ? `color-mix(in oklab, ${colorObj?.oklch} 100%, #000 40%)`
            : (colorObj?.text_color ?? '#FFFFFF')
    );
</script>

{#if labelOnly}
    <span
        data-slot="account-badge"
        style:--bg-color={bgColor}
        style:--text-color={textColor}
        class={cn('font-semibold text-(--text-color)', _class)}>
        {account.name}
    </span>
{:else}
    <Badge
        --bg-color={bgColor}
        --text-color={textColor}
        data-slot="account-badge"
        class={cn(
            'rounded border-none bg-(--bg-color) px-2 font-semibold text-(--text-color)',
            _class
        )}>
        {account.name}
    </Badge>
{/if}
