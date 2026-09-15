<script lang="ts">
    import type { SelectOption } from '@components/ui/forms/combobox.svelte';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';
    import Combobox from '@components/ui/forms/combobox.svelte';

    interface Props {
        value?: string | number;
        endpoint?: string;
        accounts?: App.Models.Account[];
        placeholder?: string;
        clearable?: boolean;
        class?: string;
    }

    let {
        value = $bindable(),
        accounts = [],
        endpoint,
        placeholder = 'Select account',
        clearable = true,
        class: _class,
    }: Props = $props();

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

    const resolvedAccounts = $derived(
        accounts.length ? accounts : (page.props?.static?.accounts ?? [])
    );

    const options = $derived(
        resolvedAccounts.map((account) => ({ value: String(account.id), label: account.name }))
    );

    /** Falls back to the endpoint URL — the Combobox fetches it via `useHttp`. */
    const comboboxOptions = $derived(resolvedAccounts.length ? options : endpoint);

    function findAccount(val: string | number | undefined): App.Models.Account | undefined {
        if (val === undefined || val === '') {
            return undefined;
        }

        return resolvedAccounts.find((account) => String(account.id) === String(val));
    }

    const selectedAccount = $derived(findAccount(value));

    function getValue(): string {
        return selectedAccount ? String(selectedAccount.id) : '';
    }

    /** Clearing resolves to `undefined`, matching the optional id shape used by the transaction forms. */
    function setValue(next: string | string[]): void {
        const first = Array.isArray(next) ? next[0] : next;
        const account = findAccount(first);
        value = account?.id ?? undefined;
    }
</script>

<div class={cn('relative w-full', _class)}>
    <Combobox
        {clearable}
        inputProps={{ class: cn('placeholder:text-base-content/35', selectedAccount && 'pl-9') }}
        option={accountOption}
        options={comboboxOptions}
        {placeholder}
        sideTrigger
        bind:value={getValue, setValue} />

    {#if selectedAccount}
        <span
            class="pointer-events-none absolute top-1/2 left-2.5 z-10 flex -translate-y-1/2 items-center">
            {@render chip(selectedAccount)}
        </span>
    {/if}
</div>

{#snippet accountOption(opt: SelectOption)}
    {@const account = findAccount(opt.value)}

    <div class="flex w-full min-w-0 items-center gap-2.5">
        {#if account}
            {@render chip(account)}
        {/if}
        <span class="truncate text-sm font-medium">{opt.label}</span>
    </div>
{/snippet}

{#snippet chip(acct: App.Models.Account)}
    {@const badge = badgeVisual(acct)}

    <DecorationBadge
        background={badge.background}
        color={badge.color}
        icon={badge.icon}
        size="sm"
        text={badge.text} />
{/snippet}
