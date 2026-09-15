<script lang="ts">
    import type {
        ComboboxTriggerContext,
        SelectOption,
    } from '@components/ui/forms/combobox.svelte';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';
    import { Combobox as ComboboxPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';
    import Combobox from '@components/ui/forms/combobox.svelte';

    /** The account travels with its option — no lookups needed downstream. */
    interface AccountOption extends SelectOption {
        account: App.Models.Account;
    }

    interface Props {
        value?: string | number;
        accounts?: App.Models.Account[];
        placeholder?: string;
        clearable?: boolean;
        class?: string;
    }

    let {
        value = $bindable(),
        accounts = [],
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

    const comboboxOptions = $derived(
        resolvedAccounts.map((account) => ({
            value: String(account.id),
            label: account.name,
            account,
        }))
    );

    function findAccount(val: string | number | undefined): App.Models.Account | undefined {
        if (val === undefined || val === '') {
            return undefined;
        }

        return resolvedAccounts.find((account) => String(account.id) === String(val));
    }

    const selectedAccount = $derived(findAccount(value));

    let inputRef = $state<HTMLInputElement | null>(null);
    let open = $state(false);

    // Mirror the selected label onto the search input while the dropdown is closed
    // (bits-ui only refreshes the display on selection events).
    $effect(() => {
        if (inputRef && !open) {
            inputRef.value = selectedAccount?.name ?? '';
        }
    });

    function getValue(): string {
        return selectedAccount ? String(selectedAccount.id) : '';
    }

    /** Clearing resolves to `undefined`, matching the optional id shape used by the transaction forms. */
    function setValue(next: string): void {
        const account = findAccount(next);
        value = account?.id ?? undefined;
    }
</script>

<Combobox
    {clearable}
    contentProps={{ sideOffset: 10 }}
    inputProps={{ class: 'placeholder:text-base-content/35' }}
    {option}
    options={comboboxOptions}
    {placeholder}
    {trigger}
    bind:value={getValue, setValue} />

{#snippet chip(account: App.Models.Account)}
    {const badge = badgeVisual(account)}

    <DecorationBadge
        background={badge.background}
        color={badge.color}
        icon={badge.icon}
        size="sm"
        text={badge.text} />
{/snippet}

{#snippet option({ account, label }: AccountOption)}
    <div class="flex w-full min-w-0 items-center gap-2.5">
        {@render chip(account)}
        <span class="truncate text-sm font-medium">{label}</span>
    </div>
{/snippet}

{#snippet trigger({
    inputProps,
    triggerProps,
    selected,
    open,
}: ComboboxTriggerContext<AccountOption>)}
    {const account = selected?.account}

    <ComboboxPrimitive.Trigger
        {...triggerProps}
        class={cn(
            'relative flex min-h-10 w-full items-center rounded-lg py-1 text-left transition-colors focus-within:bg-base-content/5 hover:bg-base-content/5',
            _class
        )}>
        {#if account}
            <span
                class="pointer-events-none absolute top-1/2 left-2.5 z-10 flex -translate-y-1/2 items-center">
                {@render chip(account)}
            </span>
        {/if}

        <ComboboxPrimitive.Input
            {...inputProps}
            class={cn(
                'w-full bg-transparent text-sm font-medium outline-none placeholder:text-base-content/35',
                account ? 'pr-16 pl-14' : 'px-2.5'
            )}
            aria-label={placeholder}
            autocomplete="off"
            bind:ref={inputRef} />

        {#if clearable && account}
            <button
                class="absolute top-1/2 right-9 z-10 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-base-content/40 hover:bg-base-content/10 hover:text-base-content"
                aria-label="Hapus akun"
                onclick={() => setValue('')}
                onpointerdown={(e) => e.stopPropagation()}
                type="button">
                <i class="iconify size-4 solar--close-linear"></i>
            </button>
        {/if}

        <i
            class="pointer-events-none absolute top-1/2 right-2.5 iconify flex size-4 -translate-y-1/2 items-center text-base-content solar--alt-arrow-down-linear"
            class:rotate-180={open}></i>
    </ComboboxPrimitive.Trigger>
{/snippet}
