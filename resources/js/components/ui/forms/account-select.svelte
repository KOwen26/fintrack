<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';
    import Svelecte from 'svelecte';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';

    interface Props {
        value?: string;
        endpoint?: string;
        accounts?: App.Models.Account[];
        placeholder?: string;
        class?: string;
    }

    let {
        value = $bindable(),
        accounts = [],
        placeholder = 'Select account',
        class: _class,
        ...props
    }: Props = $props();

    const options = $derived(accounts.length ? accounts : page.props?.static?.accounts);

    interface BadgeVisual {
        icon?: string;
        text?: string;
        background?: string;
        color?: string;
    }

    function badgeVisual(acct: App.Models.Account): BadgeVisual {
        const icon = getDecorationIcon(acct.decorations.icon)?.value;
        const hex = getDecorationColor(acct.decorations.color)?.hex;

        return {
            icon,
            text: icon ? undefined : StringHelper.getInitials(acct?.name),
            background: hex ? `${hex}20` : undefined,
            color: hex ?? undefined,
        };
    }
</script>

<div class={cn('account-select w-full', _class)}>
    {#snippet chip(acct: App.Models.Account)}
        {const badge = badgeVisual(acct)}

        <DecorationBadge
            background={badge.background}
            color={badge.color}
            icon={badge.icon}
            size="sm"
            text={badge.text} />
    {/snippet}

    <Svelecte
        class="w-full"
        labelField="name"
        {options}
        {placeholder}
        valueField="id"
        bind:value
        {...props}>
        {#snippet selection(selectedOptions: App.Models.Account[])}
            {#each selectedOptions as account (account.id)}
                <!-- svelte-ignore a11y_no_static_element_interactions -->
                <div
                    class="flex min-w-0 items-center gap-2.5"
                    onmousedown={(e) => e.preventDefault()}>
                    {@render chip(account)}

                    <span class="truncate text-sm font-medium">{account?.name}</span>
                </div>
            {/each}
        {/snippet}

        {#snippet option(account: App.Models.Account)}
            <div class="sv-item--content flex items-center gap-2.5">
                {@render chip(account)}

                <span class="truncate text-sm font-medium">{account?.name}</span>
            </div>
        {/snippet}

        {#snippet toggleIcon()}
            <i class="iconify size-6 text-base-content/40 solar--alt-arrow-down-outline"></i>
        {/snippet}
    </Svelecte>
</div>

<style>
    /* Borderless picker row (mockup Design A) — overrides the global Svelecte skin */
    .account-select {
        --sv-border: 0;
        --sv-control-bg: transparent;
        --sv-min-height: 2.25rem;
        --sv-selection-gap: 4px;
        --sv-placeholder-color: color-mix(in oklab, var(--color-base-content) 35%, transparent);
        --sv-icon-color: color-mix(in oklab, var(--color-base-content) 40%, transparent);
        --sv-icon-color-hover: var(--color-primary);
    }

    .account-select :global(.sv-control:hover),
    .account-select :global(.svelecte.is-focused .sv-control) {
        background-color: color-mix(in oklab, var(--color-base-content) 5%, transparent);
    }
</style>
