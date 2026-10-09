<script lang="ts">
    import type { DecorationBadgeSize } from '@components/module/decoration-badge.svelte';
    import type {
        ComboboxTriggerContext,
        SelectOption,
    } from '@components/ui/forms/combobox.svelte';
    import type { Decoration } from '@type/generated';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';
    import { Combobox as ComboboxPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Collapsible from '@components/ui/collapsible.svelte';
    import Combobox from '@components/ui/forms/combobox.svelte';
    import { inputGroupClasses } from '@components/ui/forms/input.svelte';
    import Modal from '@components/ui/modals/modal.svelte';
    import Popover from '@components/ui/popover.svelte';
    import ScrollArea from '@components/ui/scroll-area.svelte';

    type Cashflow = 'inflow' | 'outflow';

    /** Grouped catalog row — straight from `static.categories` / the backend shape. */
    interface CategoryOption {
        id: string;
        name: string;
        decorations?: Decoration;
        options?: CategoryOption[];
    }

    type GroupNode = CategoryOption & { children: CategoryOption[] };

    type ListLayout = 'list' | 'grid-2' | 'icon';

    interface Props {
        value?: string;
        /** Restricts the shared catalog to one money direction (income vs expense forms). */
        cashflow?: Cashflow;
        placeholder?: string;
        variant?: 'inline' | 'popover' | 'modal' | 'combobox';
        clearable?: boolean;
        /** How groups render: toggleable sections or plain divider labels. Groups are never selectable. */
        groupVariant?: 'collapsible' | 'text';
        /** Row arrangement: single column rows, 2-column (chip + text), or icon tiles (chip top, text below, 4 columns). */
        optionVariant?: ListLayout;
        class?: string;
        disabled?: boolean;
        /** Required-ness flag for API symmetry. The button trigger cannot carry
            `aria-required` (unsupported on role=button) — the visible marker
            and semantics belong to the wrapping Field. */
        required?: boolean;
    }

    let {
        value = $bindable(),
        cashflow = undefined,
        placeholder = 'Select Category',
        variant = 'combobox',
        clearable = true,
        groupVariant = 'collapsible',
        optionVariant = 'list',
        class: _class,
        disabled = false,
        required = false,
    }: Props = $props();

    interface CategoryComboboxOption extends SelectOption {
        category: CategoryOption;
        group: string;
    }

    const triggerClass = cn(
        inputGroupClasses,
        'gap-2.5 rounded-lg px-3 py-1 text-left hover:bg-base-content/5'
    );

    const gridCols: Record<Exclude<ListLayout, 'list'>, string> = {
        'grid-2': 'grid grid-cols-2 gap-1',
        icon: 'grid grid-cols-4 gap-1',
    };

    function toGroups(source: CategoryOption[]): GroupNode[] {
        return source.map((group) => ({
            ...group,
            children: group.options ?? [],
        }));
    }

    const groups = $derived.by(() => {
        const shared = (page.props?.static?.categories ?? {}) as Record<string, CategoryOption[]>;

        return toGroups(
            cashflow ? (shared[`grouped_${cashflow}`] ?? []) : Object.values(shared).flat()
        );
    });

    /** Flat child list with its owning group — one traversal for selection, combobox and reveal. */
    const flat = $derived(
        groups.flatMap((group) => group.children.map((child) => ({ child, group })))
    );

    const selectedEntry = $derived(flat.find(({ child }) => child.id === value));
    const selected = $derived(selectedEntry?.child);

    const comboboxOptions = $derived(
        flat.map(({ child, group }) => ({
            value: child.id,
            label: `${group.name} ${child.name}`,
            category: child,
            group: group.name,
        }))
    );

    function getComboboxValue(): string {
        return selected?.id ?? '';
    }

    /** Clearing resolves to `undefined`, matching the optional category id shape. */
    function setComboboxValue(next: string): void {
        value = flat.some(({ child }) => child.id === next) ? next : undefined;
    }

    function isSelected(child: CategoryOption): boolean {
        return value === child.id;
    }

    function pick(child: CategoryOption): void {
        if (disabled) return;

        if (variant === 'inline') {
            // Toggle semantics, mirroring decoration-color-selector's aria-pressed swatches.
            value = isSelected(child) ? undefined : child.id;

            return;
        }

        value = child.id;
        open = false;
    }

    let comboboxInputRef = $state<HTMLInputElement | null>(null);
    let comboboxOpen = $state(false);

    // Mirror the selected category name onto the search input while closed;
    // bits-ui only refreshes the display on selection events.
    $effect(() => {
        if (comboboxInputRef && !comboboxOpen) {
            comboboxInputRef.value = selected?.name ?? '';
        }
    });

    let open = $state(false);
    let expanded = $state<Record<string, boolean>>(buildInitialExpanded());

    /** All groups start collapsed; the group holding the current selection starts expanded. */
    function buildInitialExpanded(): Record<string, boolean> {
        const initial: Record<string, boolean> = {};

        for (const group of groups) {
            initial[group.id] = false;
        }

        if (selectedEntry) {
            initial[selectedEntry.group.id] = true;
        }

        return initial;
    }

    function revealSelectedGroup(): void {
        if (selectedEntry) {
            expanded[selectedEntry.group.id] = true;
        }
    }

    function toggleContainer(): void {
        if (disabled) return;

        if (!open) {
            revealSelectedGroup();
        }

        open = !open;
    }

    function handleOpenChange(next: boolean): void {
        // Reveal the group holding the current selection when opening.
        if (next) {
            revealSelectedGroup();
        }
    }
</script>

<div class={cn('w-full', _class)}>
    {#if variant === 'combobox'}
        <Combobox
            {clearable}
            contentProps={{ sideOffset: 10 }}
            {disabled}
            inputProps={{
                class: 'placeholder:text-base-content/35',
                'aria-required': required || undefined,
            }}
            option={comboboxOption}
            options={comboboxOptions}
            {placeholder}
            trigger={comboboxTrigger}
            bind:open={comboboxOpen}
            bind:value={getComboboxValue, setComboboxValue} />
    {:else if variant === 'popover'}
        <Popover
            {triggerClass}
            align="start"
            rootProps={{ onOpenChange: handleOpenChange }}
            triggerProps={{ disabled }}
            bind:open>
            {#snippet trigger()}
                {@render triggerContent()}
            {/snippet}

            {@render categoryList()}
        </Popover>
    {:else}
        <!-- inline & modal share the plain trigger button; inline expands the list in place -->
        <button
            class={triggerClass}
            aria-expanded={open}
            aria-haspopup={variant === 'modal' ? 'dialog' : undefined}
            {disabled}
            onclick={toggleContainer}
            type="button">
            {@render triggerContent()}
        </button>

        {#if variant === 'modal'}
            <Modal title="Pilih Kategori" bind:open>
                {@render categoryList()}
            </Modal>
        {:else if open}
            <hr class="mt-1 border-base-content/10" />
            {@render categoryList()}
        {/if}
    {/if}
</div>

<!-- Trigger row content, shared by all variants. -->
{#snippet triggerContent()}
    {#if selected}
        {@render chip(selected.decorations, selected.name, true)}
        <span class="truncate text-sm font-medium">{selected.name}</span>
    {:else}
        <span class="truncate text-sm text-base-content/35">{placeholder}</span>
    {/if}
    <i
        class="ml-auto iconify size-4 shrink-0 text-base-content transition-transform duration-200 solar--alt-arrow-down-linear"
        class:rotate-180={open}></i>
{/snippet}

{#snippet comboboxOption({ category }: CategoryComboboxOption)}
    <div class="flex w-full min-w-0 items-center gap-2.5 pr-5">
        {@render chip(category.decorations, category.name)}
        <span class="truncate text-sm font-medium">{category.name}</span>
    </div>
{/snippet}

{#snippet comboboxTrigger({
    inputProps,
    triggerProps,
    selected,
    open,
}: ComboboxTriggerContext<CategoryComboboxOption>)}
    {const category = $derived(selected?.category)}

    <ComboboxPrimitive.Trigger
        {...triggerProps}
        class={cn(
            inputGroupClasses,
            'relative px-3 hover:bg-base-content/5',
            'disabled:cursor-not-allowed disabled:border-input disabled:bg-base-content/10',
            _class
        )}>
        {#if category}
            <span
                class="pointer-events-none absolute top-1/2 left-2.5 z-10 flex -translate-y-1/2 items-center">
                {@render chip(category.decorations, category.name)}
            </span>
        {/if}

        <ComboboxPrimitive.Input
            {...inputProps}
            class={cn(
                'h-full w-full bg-transparent text-sm font-medium outline-none placeholder:text-base-content/80',
                category ? 'pr-16 pl-10' : ''
            )}
            aria-label={placeholder}
            autocomplete="off"
            bind:ref={comboboxInputRef} />

        {#if clearable && category && !disabled}
            <button
                class="absolute top-1/2 right-9 z-10 flex size-6 -translate-y-1/2 items-center justify-center rounded-full text-base-content/60 hover:bg-base-content/10 hover:text-base-content"
                aria-label="Clear category"
                onclick={() => setComboboxValue('')}
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

<!-- Grouped list, shared by all variants. Groups are separated by `hr`; groups are never selectable. -->
{#snippet categoryList()}
    <ScrollArea rootClass="max-h-150 space-y-0.5 overflow-y-auto">
        {#each groups as group, index (group.id)}
            {#if index > 0}
                <hr class="my-1 border-base-content/10" />
            {/if}

            {#if group.children.length && groupVariant === 'collapsible'}
                <Collapsible bind:open={expanded[group.id]}>
                    {#snippet trigger()}
                        <div
                            class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left hover:bg-base-content/5"
                            aria-expanded={expanded[group.id]}>
                            {@render chip(group.decorations, group.name, false)}
                            <span class="truncate text-sm font-semibold">{group.name}</span>
                            <i
                                class="ml-auto iconify size-4 shrink-0 text-base-content transition-transform duration-200 solar--alt-arrow-down-linear"
                                class:rotate-180={expanded[group.id]}></i>
                        </div>
                    {/snippet}

                    {#snippet content()}
                        {@render groupChildren(group)}
                    {/snippet}
                </Collapsible>
            {:else}
                {@render groupLabel(group.name)}
                {@render groupChildren(group)}
            {/if}
        {/each}

        {#if !groups.length}
            <p class="px-2 py-1.5 text-sm text-base-content/35">Tidak ada kategori</p>
        {/if}
    </ScrollArea>
{/snippet}

{#snippet groupChildren(group: GroupNode)}
    <div
        class={cn(
            'py-0.5',
            optionVariant === 'list' &&
                groupVariant === 'collapsible' &&
                'ml-6 space-y-0.5 border-l border-base-content/10 pl-2',
            optionVariant !== 'list' && gridCols[optionVariant]
        )}>
        {#each group.children as child (child.id)}
            {@render childRow(child)}
        {/each}
    </div>
{/snippet}

{#snippet groupLabel(name: string)}
    <p class="px-2 pt-2 pb-1 text-2xs font-bold tracking-widest text-base-content/40 uppercase">
        {name}
    </p>
{/snippet}

{#snippet childRow(child: CategoryOption)}
    {@const active = isSelected(child)}
    <button
        class={cn(
            'flex w-full transition hover:bg-base-content/5 disabled:opacity-50',
            optionVariant === 'icon'
                ? 'flex-col items-center gap-1.5 rounded-xl px-2 py-2.5 text-center'
                : 'min-w-0 items-center gap-2.5 rounded-lg px-2 py-1.5 text-left',
            active && 'bg-primary/10'
        )}
        aria-pressed={active}
        {disabled}
        onclick={() => pick(child)}
        type="button">
        {@render chip(
            child.decorations,
            child.name,
            active,
            optionVariant === 'icon' ? 'md' : 'sm'
        )}
        <span
            class={cn(
                optionVariant === 'icon'
                    ? 'line-clamp-2 w-full text-xs leading-tight'
                    : 'truncate text-sm',
                active && 'text-primary'
            )}>
            {child.name}
        </span>
    </button>
{/snippet}

<!-- Chip: resolves decoration slugs to icon/hex, falls back to name initials. -->
{#snippet chip(
    decorations: Decoration | undefined,
    name: string | undefined,
    active: boolean = false,
    size: DecorationBadgeSize = 'sm'
)}
    {@const icon = getDecorationIcon(decorations?.icon)?.value}
    {@const hex = getDecorationColor(decorations?.color)?.hex}
    <DecorationBadge
        class={cn(active && 'ring ring-primary ring-offset-2')}
        background={hex ? `${hex}20` : undefined}
        color={hex ?? undefined}
        {icon}
        {size}
        text={icon ? undefined : StringHelper.getInitials(name)} />
{/snippet}

<style>
    button:disabled {
        cursor: not-allowed;
    }
</style>
