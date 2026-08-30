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

    let { account, class: _class, children, ...props }: Props = $props();

    const colorObj = $derived(
        account.decorations?.color ? getDecorationColor(account.decorations.color) : undefined
    );

    const color = $derived(colorObj?.hex);
</script>

<div
    style={color ? `--accent:${color};border-color:${color}50` : undefined}
    class={cn(
        'group/card relative isolate flex min-h-20 flex-col rounded-xl border border-base-200 bg-white p-5 transition-shadow hover:shadow-md',
        _class
    )}
    {...props}>
    <div class="relative z-2 flex flex-1 flex-col">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-1 items-center gap-2">
                <h3 class="truncate font-semibold text-base-content">{account.name}</h3>
                {#if account.access_type === 'joint'}
                    <span
                        class="shrink-0 rounded-full bg-base-200 px-2 py-0.5 text-xs font-medium tracking-wide text-base-content">
                        Joint
                    </span>
                {/if}
            </div>
            <AccountTypeBadge icon="only" type={account.type} />
        </div>

        <div class="mt-auto pt-3">
            <p class="text-2xl font-bold tracking-tight text-base-content">
                {Formatter.currency(account.current_balance)}
            </p>
        </div>

        <div
            class="mt-2 flex items-center gap-1.5 border-t border-(--accent)/50 pt-2 text-xs text-base-content/50">
            <i class="iconify size-4 ph--bank-bold"></i>
            <span>{account.provider?.name ?? '-'}</span>
        </div>
    </div>
</div>

<style>
    /* ── Diagonal gradient wash ── */
    .group\/card::before {
        content: '';
        position: absolute;
        z-index: 0;
        inset: 0;
        border-radius: inherit;
        background: linear-gradient(
            to bottom right,
            var(--accent, transparent) 0%,
            transparent 85%
        );
        opacity: 0.2;
        transition: opacity 0.4s ease;
        pointer-events: none;
    }

    .group\/card:hover::before {
        opacity: 0.3;
    }

    /* ── Accent edge strip ── */
    .group\/card::after {
        content: '';
        position: absolute;
        left: 8px;
        top: 8px;
        bottom: 8px;
        width: 6px;
        border-radius: 3px;
        background: var(--accent, var(--color-base-300));
        z-index: 1;
        pointer-events: none;
    }
</style>
