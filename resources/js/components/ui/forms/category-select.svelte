<script lang="ts">
    import type {
        DecorationBadgeItem,
        DecorationBadgeSize,
    } from '@components/ui/decoration-badge.svelte';
    import type { DecorationData } from '@type/generated';

    import ScrollArea from '../scroll-area.svelte';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { page } from '@inertiajs/svelte';

    import { cn } from '@utilities/shadcn';
    import StringHelper from '@utilities/string-helper';

    import Collapsible from '@components/ui/collapsible.svelte';
    import DecorationBadge from '@components/ui/decoration-badge.svelte';
    import Modal from '@components/ui/modals/modal.svelte';
    import Popover from '@components/ui/popover.svelte';

    /** Permissive node shape — accepts full models, lightweight `{ id, name, children }`, and `{ options }` groups. */
    interface CategoryOption {
        id: number;
        name: string;
        decorations?: DecorationData;
        options?: CategoryOption[];
        children?: CategoryOption[];
    }

    type ListLayout = 'list' | 'grid-2' | 'icon';

    interface Props {
        value?: string | number;
        categories?: CategoryOption[];
        placeholder?: string;
        variant?: 'inline' | 'popover' | 'modal';
        /** How groups render: toggleable sections or plain divider labels. Groups are never selectable. */
        groupVariant?: 'collapsible' | 'text';
        /** Row arrangement: single column rows, 2-column (chip + text), or icon tiles (chip top, text below, 4 columns). */
        optionVariant?: ListLayout;
        class?: string;
        disabled?: boolean;
    }

    let {
        value = $bindable(),
        categories = [],
        placeholder = 'Pilih kategori',
        variant = 'inline',
        groupVariant = 'collapsible',
        optionVariant = 'list',
        class: _class,
        disabled = false,
    }: Props = $props();

    interface CategoryNode {
        id: number;
        name: string;
        visual: DecorationBadgeItem;
        children: CategoryNode[];
    }

    const triggerClass =
        'flex w-full min-w-0 items-center gap-2.5 rounded-lg py-0.5 text-left disabled:opacity-50';

    const gridCols: Record<Exclude<ListLayout, 'list'>, string> = {
        'grid-2': 'grid grid-cols-2 gap-1',
        icon: 'grid grid-cols-4 gap-1',
    };

    // ── Node visuals: decoration icon/color win, initials are the fallback ──
    function nodeVisual(
        decorations: DecorationData | undefined,
        name: string | undefined
    ): DecorationBadgeItem {
        const icon = getDecorationIcon(decorations?.icon)?.value;
        const hex = getDecorationColor(decorations?.color)?.hex;

        return {
            icon,
            text: icon ? undefined : StringHelper.getInitials(name),
            background: hex ? `${hex}20` : undefined,
            color: hex ?? undefined,
        };
    }

    function toNode(raw: CategoryOption): CategoryNode {
        return {
            id: raw.id,
            name: raw.name,
            visual: nodeVisual(raw.decorations, raw.name),
            children: [],
        };
    }

    function toGroups(source: CategoryOption[]): CategoryNode[] {
        return source.map((group) => ({
            id: group.id,
            name: group.name,
            visual: nodeVisual(group.decorations, group.name),
            children: (group.options ?? group.children ?? []).map(toNode),
        }));
    }

    /**
     * Grouped tree. Preference: a grouped `categories` prop (options/children
     * arrays), then the shared `static.groupedCategories`, then the flat prop
     * rendered as ungrouped rows.
     */
    function computeGroups(): CategoryNode[] {
        const prop = categories?.length ? categories : undefined;
        const shared = (page.props?.static?.groupedCategories ?? []) as CategoryOption[];

        if (prop?.some((item) => Array.isArray(item.options) || Array.isArray(item.children))) {
            return toGroups(prop);
        }

        if (shared.length) {
            return toGroups(shared);
        }

        return (prop ?? []).map(toNode);
    }

    const groups = $derived(computeGroups());

    const selected = $derived.by(() => {
        const id = Number(value);

        if (!id) return undefined;

        for (const group of groups) {
            if (group.id === id) return group;

            const child = group.children.find((node) => node.id === id);

            if (child) return child;
        }

        return undefined;
    });

    /** All groups start collapsed; the group holding the current selection starts expanded. */
    function buildInitialExpanded(): Record<number, boolean> {
        const initial: Record<number, boolean> = {};

        for (const group of computeGroups()) {
            initial[group.id] = false;
        }

        if (selected) {
            const root = computeGroups().find(
                (group) =>
                    group.id === selected.id ||
                    group.children.some((child) => child.id === selected.id)
            );

            if (root) {
                initial[root.id] = true;
            }
        }

        return initial;
    }

    let open = $state(false);
    let expanded = $state<Record<number, boolean>>(buildInitialExpanded());

    function isSelected(node: CategoryNode): boolean {
        return Number(value) === node.id;
    }

    /** Toggle semantics, mirroring decoration-color-selector's aria-pressed swatches. Inline only. */
    function toggleSelect(node: CategoryNode): void {
        if (disabled) return;

        value = isSelected(node) ? undefined : node.id;
    }

    function pick(node: CategoryNode): void {
        if (variant === 'inline') {
            toggleSelect(node);

            return;
        }

        value = node.id;
        open = false;
    }

    function revealSelectedGroup(): void {
        if (!selected) return;

        const root = groups.find(
            (group) =>
                group.id === selected.id || group.children.some((child) => child.id === selected.id)
        );

        if (root) {
            expanded[root.id] = true;
        }
    }

    function openContainer(): void {
        if (disabled) return;

        open = true;
        revealSelectedGroup();
    }

    function toggleContainer(): void {
        if (disabled) return;

        if (open) {
            open = false;

            return;
        }

        openContainer();
    }

    function handleOpenChange(next: boolean): void {
        // Reveal the group holding the current selection when opening.
        if (next) {
            revealSelectedGroup();
        }
    }
</script>

<div class={cn('w-full', _class)}>
    {#if variant === 'popover'}
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
            onclick={variant === 'modal' ? openContainer : toggleContainer}
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
        {@render chip(selected.visual, true)}
        <span class="truncate text-sm font-medium">{selected.name}</span>
    {:else}
        <span class="truncate text-sm text-base-content/35">{placeholder}</span>
    {/if}
    <i
        class="ml-auto iconify size-4 shrink-0 text-base-content/40 transition-transform duration-200 tabler--chevron-down"
        class:rotate-180={open}></i>
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
                            {@render chip(group.visual, false)}
                            <span class="truncate text-sm font-semibold">{group.name}</span>
                            <i
                                class="ml-auto iconify size-4 shrink-0 text-base-content/40 transition-transform duration-200 tabler--chevron-down"
                                class:rotate-180={expanded[group.id]}></i>
                        </div>
                    {/snippet}

                    {#snippet content()}
                        <div
                            class={cn(
                                'py-0.5',
                                optionVariant === 'list' &&
                                    'ml-6 space-y-0.5 border-l border-base-content/10 pl-2',
                                optionVariant !== 'list' && gridCols[optionVariant]
                            )}>
                            {#each group.children as child (child.id)}
                                {@render childRow(child)}
                            {/each}
                        </div>
                    {/snippet}
                </Collapsible>
            {:else}
                {@render groupLabel(group.name)}

                <div
                    class={cn(
                        optionVariant === 'list' && 'space-y-0.5',
                        optionVariant !== 'list' && gridCols[optionVariant]
                    )}>
                    {#each group.children as child (child.id)}
                        {@render childRow(child)}
                    {/each}
                </div>
            {/if}
        {/each}

        {#if !groups.length}
            <p class="px-2 py-1.5 text-sm text-base-content/35">Tidak ada kategori</p>
        {/if}
    </ScrollArea>
{/snippet}

{#snippet groupLabel(name: string)}
    <p class="px-2 pt-2 pb-1 text-2xs font-bold tracking-widest text-base-content/40 uppercase">
        {name}
    </p>
{/snippet}

{#snippet childRow(child: CategoryNode)}
    {#if optionVariant === 'icon'}
        <!-- Tile: chip on top, text below -->
        <button
            class={cn(
                'flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-2.5 text-center transition hover:bg-base-content/5 disabled:opacity-50',
                isSelected(child) && 'bg-primary/10'
            )}
            aria-pressed={isSelected(child)}
            {disabled}
            onclick={() => pick(child)}
            type="button">
            {@render chip(child.visual, isSelected(child), 'md')}
            <span
                class={cn(
                    'line-clamp-2 w-full text-xs leading-tight',
                    isSelected(child) && 'text-primary'
                )}>
                {child.name}
            </span>
        </button>
    {:else}
        <!-- Row: chip + text side by side (list & grid-2) -->
        <button
            class={cn(
                'flex w-full min-w-0 items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition hover:bg-base-content/5 disabled:opacity-50',
                isSelected(child) && 'bg-primary/10'
            )}
            aria-pressed={isSelected(child)}
            {disabled}
            onclick={() => pick(child)}
            type="button">
            {@render chip(child.visual, isSelected(child))}
            <span class={cn('truncate text-sm', isSelected(child) && 'text-primary')}>
                {child.name}
            </span>
        </button>
    {/if}
{/snippet}

{#snippet chip(
    visual: DecorationBadgeItem,
    active: boolean = false,
    size: DecorationBadgeSize = 'sm'
)}
    <DecorationBadge
        class={cn(active && 'ring ring-primary ring-offset-2')}
        background={visual.background}
        color={visual.color}
        icon={visual.icon}
        {size}
        text={visual.text} />
{/snippet}

<style>
    button:disabled {
        cursor: not-allowed;
    }
</style>
