<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';
    import { Combobox } from 'bits-ui';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';

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

    interface AccountOption {
        value: string;
        label: string;
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

    let searchValue = $state('');

    const filteredItems = $derived(
        searchValue.length
            ? options.filter((item) => item.label.toLowerCase().includes(searchValue.toLowerCase()))
            : options
    );

    function findAccount(val: string | number | undefined): App.Models.Account | undefined {
        if (val === undefined || val === '') {
            return undefined;
        }

        return resolvedAccounts.find((account) => String(account.id) === String(val));
    }

    const selectedAccount = $derived(findAccount(value));

    let internalValue = $state('');
    let open = $state(false);
    let inputRef = $state<HTMLInputElement | null>(null);

    // Keep the Combobox value in step with externally-driven `value` changes (form resets),
    // and mirror the selected label onto the search input when the dropdown is closed.
    $effect(() => {
        internalValue = selectedAccount ? String(selectedAccount.id) : '';

        if (inputRef && !open) {
            inputRef.value = selectedAccount?.name ?? '';
        }
    });

    function applyValue(next: string): void {
        const account = findAccount(next);

        // Deselect — reuse the full clear path: the dropdown is open here, so the
        // display-sync effect skips itself and the search text must be reset directly.
        if (!account) {
            reset();

            return;
        }

        value = account.id;
    }

    function reset(): void {
        value = undefined;
        searchValue = '';

        // The input's display is imperative while bits-ui owns it (esp. when open) —
        // the display-sync effect skips itself while `open`, so clear it directly.
        if (inputRef) {
            inputRef.value = '';
        }
    }

    function clear(e: MouseEvent): void {
        e.stopPropagation();
        e.preventDefault();
        reset();
    }
</script>

<Combobox.Root
    items={filteredItems}
    onOpenChangeComplete={() => (searchValue = '')}
    onValueChange={applyValue}
    type="single"
    bind:value={internalValue}
    bind:open>
    <Combobox.Trigger
        class={cn(
            'relative flex min-h-10 w-full items-center rounded-lg py-1 text-left transition-colors focus-within:bg-base-content/5 hover:bg-base-content/5',
            _class
        )}>
        {#if selectedAccount}
            <span
                class="pointer-events-none absolute top-1/2 left-2.5 z-10 flex -translate-y-1/2 items-center">
                {@render chip(selectedAccount)}
            </span>
        {/if}

        <Combobox.Input
            class={cn(
                'w-full bg-transparent text-sm font-medium outline-none placeholder:text-base-content/35',
                selectedAccount ? 'pr-16 pl-12' : 'px-2.5'
            )}
            aria-label={placeholder}
            autocomplete="off"
            oninput={(e) => (searchValue = e.currentTarget.value)}
            {placeholder}
            bind:ref={inputRef} />

        {#if clearable && selectedAccount}
            <button
                class="absolute top-1/2 right-8 z-10 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-base-content/40 hover:bg-base-content/10 hover:text-base-content"
                aria-label="Hapus akun"
                onclick={clear}
                onpointerdown={(e) => e.stopPropagation()}
                type="button">
                <i class="iconify size-3 solar--close-linear"></i>
            </button>
        {/if}

        <i
            class="pointer-events-none absolute top-1/2 right-2.5 iconify flex size-4 -translate-y-1/2 items-center text-base-content solar--alt-arrow-down-linear"
            class:rotate-180={open}></i>
    </Combobox.Trigger>

    <Combobox.Portal>
        <Combobox.Content
            class="z-50 max-h-(--bits-combobox-content-available-height) w-(--bits-combobox-anchor-width) min-w-(--bits-combobox-anchor-width) overflow-x-hidden overflow-y-auto rounded-2xl bg-popover p-1 text-popover-foreground shadow-md ring-1 ring-foreground/10 data-[side=bottom]:slide-in-from-top-2 data-[side=top]:slide-in-from-bottom-2 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95"
            side="bottom"
            sideOffset={4}>
            <Combobox.Viewport class="space-y-0.5 p-1">
                {#each filteredItems as item (item.value)}
                    <Combobox.Item
                        class="flex w-full min-w-0 cursor-default items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm outline-none select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-highlighted:bg-base-content/5"
                        label={item.label}
                        value={item.value}>
                        {#snippet children({ selected })}
                            {@render optionRow(item, selected)}
                        {/snippet}
                    </Combobox.Item>
                {:else}
                    <p class="px-2 py-1.5 text-sm text-base-content/35">Tidak ada akun</p>
                {/each}
            </Combobox.Viewport>
        </Combobox.Content>
    </Combobox.Portal>
</Combobox.Root>

{#snippet optionRow(item: AccountOption, selected: boolean)}
    {const account = findAccount(item.value)}

    {#if account}
        {@render chip(account)}
    {/if}

    <span class={cn('truncate font-medium', selected && 'text-primary')}>{item.label}</span>
    {#if selected}
        <i class="ml-auto iconify size-4 shrink-0 text-primary solar--unread-outline"></i>
    {/if}
{/snippet}

{#snippet chip(acct: App.Models.Account)}
    {const badge = badgeVisual(acct)}

    <DecorationBadge
        background={badge.background}
        color={badge.color}
        icon={badge.icon}
        size="sm"
        text={badge.text} />
{/snippet}
