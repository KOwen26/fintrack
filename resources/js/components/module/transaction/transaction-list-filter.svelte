<script lang="ts" module>
    import type { ColumnFiltersState, SortingState } from '@tanstack/svelte-table';

    /** Column ids (on the transaction list table) that this filter drives. */
    export const FILTER_COLUMNS = {
        account_id: 'account_id',
        category_id: 'category_id',
        type: 'type',
    } as const;

    /** Friendly sort keys used by the filter UI. */
    export type SortKey = 'newest' | 'oldest' | 'highest' | 'lowest';

    /** Maps a UI sort key to TanStack `SortingState`. */
    export const SORT_STATE: Record<SortKey, SortingState> = {
        newest: [
            { id: 'transaction_date', desc: true },
            { id: 'id', desc: true },
        ],
        oldest: [
            { id: 'transaction_date', desc: false },
            { id: 'id', desc: false },
        ],
        highest: [{ id: 'amount', desc: true }],
        lowest: [{ id: 'amount', desc: false }],
    };

    /** Reverse-derives the UI sort key from the table's current sorting state. */
    export function toSortKey(sorting: SortingState): SortKey {
        const primary = sorting[0];

        if (!primary) return 'newest';
        if (primary.id === 'transaction_date') return primary.desc ? 'newest' : 'oldest';
        if (primary.id === 'amount') return primary.desc ? 'highest' : 'lowest';

        return 'newest';
    }

    /** Reads an array-valued column filter (empty array when the filter is off). */
    export function getArrayFilter<T>(filters: ColumnFiltersState, columnId: string): T[] {
        const value = filters.find((filter) => filter.id === columnId)?.value;

        return Array.isArray(value) ? (value as T[]) : [];
    }
</script>

<script lang="ts">
    import type { TransactionListTable } from './transaction-list.svelte';
    import type { App } from '@wayfinder/types';
    import type { Snippet } from 'svelte';

    import { TYPE_STYLE } from './transaction-list-item.svelte';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { onDestroy } from 'svelte';

    import { cn } from '@utilities/shadcn';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import SearchInput from '@components/ui/forms/search-input.svelte';
    import ToggleGroupItem from '@components/ui/forms/toggle-group-item.svelte';
    import ToggleGroup from '@components/ui/forms/toggle-group.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props {
        accounts: {
            id: number;
            name: string;
            decorations?: { icon?: string | null; color?: string | null };
        }[];
        categories: {
            id: string;
            name: string;
            decorations?: { icon?: string | null; color?: string | null };
        }[];
        table: TransactionListTable;
    }

    let { accounts: uniqueAccounts, categories: uniqueCategories, table }: Props = $props();

    /* ── Table state (read reactively from the table atoms) ─ */

    const columnFilters = $derived(table.atoms.columnFilters.get());
    const search = $derived((table.atoms.globalFilter.get() as string | undefined) ?? '');
    const sortKey = $derived(toSortKey(table.atoms.sorting.get()));

    const accountIds = $derived(getArrayFilter<number>(columnFilters, FILTER_COLUMNS.account_id));
    const categoryIds = $derived(getArrayFilter<string>(columnFilters, FILTER_COLUMNS.category_id));
    const types = $derived(
        getArrayFilter<App.Enums.TransactionType>(columnFilters, FILTER_COLUMNS.type)
    );

    /* ── State ───────────────────────────────────────────── */

    // Sheet — `settings` groups the transaction-type enum and sort selector
    // into a single drawer, sectioned per filter (time period slots in later).
    type SheetKind = 'account' | 'category' | 'settings';

    /** Leading badge: an icon or short text on a tinted square (direct CSS). */
    interface RowBadge {
        icon?: string;
        text?: string;
        background: string;
        color: string;
    }

    /** One selectable row inside the active sheet. */
    interface SheetOption {
        key: string;
        label: string;
        selected: boolean;
        onSelect: () => void;
        badge?: RowBadge;
    }

    /**
     * One sheet section: either generated rows (`options`) or arbitrary
     * markup (`content` snippet, e.g. a date-range picker) — one of the two.
     */
    interface SheetSection {
        /** Heading rendered above the section; null keeps the sheet header-free. */
        label: string | null;
        options?: SheetOption[];
        content?: Snippet;
    }

    let sheetOpen = $state(false);
    let sheetKind = $state<SheetKind | null>(null);
    let sheetTitle = $state('');

    // Temp filters (while sheet is open)
    let tAccountIds: number[] = $state([]);
    let tCategoryIds: string[] = $state([]);
    let tTypes: string[] = $state([]);
    let tSort: SortKey = $state('newest');

    /** Debounced global filter — typing should not hammer the table on every keystroke. */
    const SEARCH_DEBOUNCE_MS = 300;
    let searchTimer: ReturnType<typeof setTimeout> | undefined;

    function handleSearchInput(value: string) {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(
            (): void => table.setGlobalFilter(value || undefined),
            SEARCH_DEBOUNCE_MS
        );
    }

    /* ── Table mutations ─────────────────────────────────── */

    // An empty array must unset the filter, otherwise the in-array filter fn
    // would reject every row.
    function setArrayFilter(columnId: string, ids: unknown[]) {
        table.getColumn(columnId)?.setFilterValue(ids.length ? [...ids] : undefined);
    }

    onDestroy(() => clearTimeout(searchTimer));

    /* ── Active filter tags ──────────────────────────────── */
    const activeFilters = $derived.by(() => {
        const tags: { label: string; icon?: string; onClear: () => void }[] = [];

        for (const id of accountIds) {
            const account = uniqueAccounts.find((x) => x.id === id);
            if (account)
                tags.push({
                    label: account.name,
                    onClear: () => {
                        setArrayFilter(
                            FILTER_COLUMNS.account_id,
                            accountIds.filter((x) => x !== id)
                        );
                    },
                });
        }
        for (const id of categoryIds) {
            const category = uniqueCategories.find((x) => x.id === id);
            if (category)
                tags.push({
                    label: category.name,
                    onClear: () => {
                        setArrayFilter(
                            FILTER_COLUMNS.category_id,
                            categoryIds.filter((x) => x !== id)
                        );
                    },
                });
        }
        for (const type of types) {
            tags.push({
                label: TYPE_STYLE[type].label,
                onClear: () => {
                    setArrayFilter(
                        FILTER_COLUMNS.type,
                        types.filter((x) => x !== type)
                    );
                },
            });
        }
        if (sortKey !== 'newest') {
            tags.push({
                label: SORT_LABELS[sortKey],
                icon: 'solar--sort-vertical-line-duotone',
                onClear: () => {
                    table.setSorting(SORT_STATE.newest);
                },
            });
        }

        return tags;
    });

    /* ── Sheet helpers ───────────────────────────────────── */

    const SHEET_TITLES: Record<SheetKind, string> = {
        account: 'Account',
        category: 'Category',
        settings: 'Settings',
    };

    function openSheet(kind: SheetKind) {
        tAccountIds = [...accountIds];
        tCategoryIds = [...categoryIds];
        tTypes = [...types];
        tSort = sortKey;

        sheetTitle = SHEET_TITLES[kind];
        sheetKind = kind;
        sheetOpen = true;
    }

    function closeSheet() {
        sheetOpen = false;
        sheetKind = null;
    }

    function applyFilter() {
        setArrayFilter(FILTER_COLUMNS.account_id, tAccountIds);
        setArrayFilter(FILTER_COLUMNS.category_id, tCategoryIds);
        setArrayFilter(FILTER_COLUMNS.type, tTypes);
        table.setSorting(SORT_STATE[tSort]);
        closeSheet();
    }

    /** Pure toggle — returns a new array with `item` added or removed. */
    function toggled<T>(list: T[], item: T): T[] {
        return list.includes(item) ? list.filter((x) => x !== item) : [...list, item];
    }

    /** Direct-CSS badge colors from an optional decoration color slug. */
    function decorationBadgeColors(color?: string | null): { background: string; color: string } {
        const hex = color ? getDecorationColor(color)?.hex : undefined;

        return {
            background: hex ? `${hex}20` : 'var(--color-popover)',
            color: hex ?? 'color-mix(in oklab, var(--color-foreground) 60%, transparent)',
        };
    }

    /* ── Chip data ─────────────────────────────────────────── */

    /** Single source for sort-key labels — active tags and sheet options. */
    const SORT_LABELS: Record<SortKey, string> = {
        newest: 'Newest',
        oldest: 'Oldest',
        highest: 'Highest',
        lowest: 'Lowest',
    };

    const sortOptions = (Object.entries(SORT_LABELS) as [SortKey, string][]).map(([id, label]) => ({
        id,
        label,
    }));

    const filterChips = $derived<{ kind: SheetKind; label: string; active: boolean }[]>([
        {
            kind: 'account',
            label:
                accountIds.length === 0
                    ? 'All Accounts'
                    : accountIds.length === 1
                      ? (uniqueAccounts.find((a) => a.id === accountIds[0])?.name ?? '1 Account')
                      : `${accountIds.length} Accounts`,
            active: accountIds.length > 0,
        },
        {
            kind: 'category',
            label:
                categoryIds.length === 0
                    ? 'All Categories'
                    : categoryIds.length === 1
                      ? (uniqueCategories.find((c) => c.id === categoryIds[0])?.name ??
                        '1 Category')
                      : `${categoryIds.length} Categories`,
            active: categoryIds.length > 0,
        },
    ]);

    /* ── Sheet content ───────────────────────────────────── */

    /**
     * Normalized option sections for the active sheet — the markup stays
     * generic. The `settings` sheet renders both filters in order, stacked
     * as its sections.
     */
    const sheetSections = $derived.by<SheetSection[]>(() => {
        switch (sheetKind) {
            case 'account':
                return [
                    {
                        label: null,
                        options: [
                            {
                                key: 'all',
                                label: 'All Accounts',
                                selected: tAccountIds.length === 0,
                                onSelect: () => (tAccountIds = []),
                                badge: {
                                    text: 'ALL',
                                    background:
                                        'color-mix(in oklab, var(--color-primary) 10%, transparent)',
                                    color: 'var(--color-primary)',
                                },
                            },
                            ...uniqueAccounts.map((acct): SheetOption => ({
                                key: String(acct.id),
                                label: acct.name,
                                selected: tAccountIds.includes(acct.id),
                                onSelect: () => (tAccountIds = toggled(tAccountIds, acct.id)),
                                badge: {
                                    // icon: 'solar--banknote-2-bold-duotone',
                                    icon: getDecorationIcon(acct.decorations?.icon)?.value,
                                    ...decorationBadgeColors(acct.decorations?.color),
                                },
                            })),
                        ],
                    },
                ];
            case 'category':
                return [
                    {
                        label: null,
                        options: [
                            {
                                key: 'all',
                                label: 'All Categories',
                                selected: tCategoryIds.length === 0,
                                onSelect: () => (tCategoryIds = []),
                                badge: {
                                    icon: 'solar--tag-bold-duotone',
                                    ...decorationBadgeColors(),
                                },
                            },
                            ...uniqueCategories.map((cat): SheetOption => ({
                                key: String(cat.id),
                                label: cat.name,
                                selected: tCategoryIds.includes(cat.id),
                                onSelect: () => (tCategoryIds = toggled(tCategoryIds, cat.id)),
                                badge: {
                                    icon: getDecorationIcon(cat.decorations?.icon)?.value,
                                    ...decorationBadgeColors(cat.decorations?.color),
                                },
                            })),
                        ],
                    },
                ];
            case 'settings':
                // The whole settings sheet is handed over to the
                // `settingContent` snippet — type selection (toggle group)
                // and sort (option rows) live there.
                return [{ label: null, content: settingContent }];
            default:
                return [];
        }
    });
</script>

<div class="flex flex-col gap-3">
    <SearchInput
        onclear={() => {
            clearTimeout(searchTimer);
            table.setGlobalFilter(undefined);
        }}
        oninput={handleSearchInput}
        placeholder="Search transactions"
        value={search} />

    <!-- ── FILTER CHIPS ─────────────────────────────────────── -->
    <div class="flex items-center gap-2">
        <!-- Sort button (standalone) -->
        <button
            class={cn(
                'border-1.5 flex size-9 shrink-0 items-center justify-center rounded-xl transition-all duration-150',
                sortKey !== 'newest'
                    ? 'border-primary bg-primary text-primary-content'
                    : 'border-border bg-card text-foreground hover:border-primary'
            )}
            aria-label="Sort"
            onclick={() => openSheet('settings')}>
            <i class="iconify size-4 solar--tuning-3-line-duotone"></i>
        </button>

        <div class="flex flex-1 scrollbar-none gap-1.5 overflow-x-auto">
            {#each filterChips as { kind, label, active }, i (i)}
                <button
                    class={cn(
                        'border-1.5 inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-sans text-xs font-semibold whitespace-nowrap transition-all duration-150',
                        active
                            ? 'border-primary bg-primary text-primary-content'
                            : 'border-border bg-card text-foreground hover:border-primary hover:text-primary'
                    )}
                    onclick={() => openSheet(kind)}>
                    <span>{label}</span>
                    <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
                </button>
            {/each}
        </div>
    </div>

    <!-- ── ACTIVE FILTER TAGS ──────────────────────────────── -->
    {#if activeFilters.length > 0}
        <div class="flex flex-wrap gap-1.5">
            {#each activeFilters as tag, i (i)}
                <button
                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 font-sans text-xs font-semibold text-primary transition-all duration-120 hover:bg-primary/20"
                    onclick={tag.onClear}>
                    {#if tag.icon}<i class="iconify size-3 {tag.icon}"></i>{/if}
                    <span>{tag.label}</span>
                    <i class="iconify size-3 solar--close-line-duotone"></i>
                </button>
            {/each}
        </div>
    {/if}

    <!-- ── BOTTOM SHEET ────────────────────────────────────── -->
    <DrawerModal
        contentClass="mx-auto w-full max-w-md rounded-t-xl"
        title={sheetTitle}
        bind:open={sheetOpen}>
        {#snippet actionButton()}
            <Button class="w-full rounded-2xl font-semibold normal-case" onclick={applyFilter}>
                Apply Filters
            </Button>
        {/snippet}

        {#each sheetSections as section, index (section.label ?? String(index))}
            {#if index > 0}
                <div class="mx-5 border-t border-border"></div>
            {/if}

            {#if section.label}
                <div class={cn('px-5 pt-3 pb-1', index > 0 && 'pt-4')}>
                    {@render sectionHeading(section.label)}
                </div>
            {/if}

            {#if section?.content}
                <div class="px-5 pt-1 pb-2">
                    {@render section.content()}
                </div>
            {:else if section?.options}
                {#each section.options as option (option.key)}
                    {@render optionRow(option)}
                {/each}
            {/if}
        {/each}
    </DrawerModal>
</div>

{#snippet optionRow(option: SheetOption)}
    <button
        class={cn(
            'flex w-full items-center gap-3 px-5 py-3 text-left font-sans transition-colors duration-100',
            option.selected ? 'bg-info/10' : 'hover:bg-card'
        )}
        onclick={option.onSelect}>
        {@render optionBadge(option.badge)}

        <div class="flex-1 text-sm font-medium text-foreground">{option.label}</div>

        {#if option.selected}
            <i class="iconify size-5 shrink-0 text-info solar--check-read-line-duotone"></i>
        {/if}
    </button>
{/snippet}

{#snippet sectionHeading(label: string)}
    <!-- Text styling only; spacing is owned by the wrapper rendering it. -->
    <div class="font-sans text-xs font-semibold tracking-wide text-muted-foreground uppercase">
        {label}
    </div>
{/snippet}

{#snippet settingContent()}
    <!-- Root: horizontal spacing comes from the drawer wrapper; each element's rhythm from its own wrapper below. -->
    <div class="flex flex-col">
        <div class="py-2">
            {@render sectionHeading('Transaction Type')}
        </div>

        <!-- Multi-select segmented toggle group over the transaction types. -->
        <div
            class="inline-flex items-stretch overflow-hidden rounded-md border border-input shadow-xs">
            <ToggleGroup
                class="w-full rounded-none"
                size="sm"
                type="multiple"
                variant="default"
                bind:value={tTypes as never}>
                {#each Object.entries(TYPE_STYLE) as [typeKey, cfg] (typeKey)}
                    <ToggleGroupItem class="px-3" value={typeKey}>
                        {cfg.label}
                    </ToggleGroupItem>
                {/each}
            </ToggleGroup>
        </div>

        <div class="my-2 border-t border-border"></div>

        <div class="pb-1">
            {@render sectionHeading('Sort')}
        </div>

        {#each sortOptions as sortOption (sortOption.id)}
            {@render optionRow({
                key: sortOption.id,
                label: sortOption.label,
                selected: tSort === sortOption.id,
                onSelect: () => (tSort = sortOption.id),
            })}
        {/each}
    </div>
{/snippet}

{#snippet optionBadge(badge)}
    {#if badge}
        <DecorationBadge
            class="size-8 rounded"
            iconClass="size-5"
            background={badge.background}
            color={badge.color}
            icon={badge.icon}
            text={badge.text} />
    {/if}
{/snippet}
