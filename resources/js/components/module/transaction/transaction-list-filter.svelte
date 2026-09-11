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
    import type { Data } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { TYPE_STYLE } from './transaction-list-item.svelte';

    import { getDecorationColor } from '@data/decoration-colors';

    import { cn } from '@utilities/shadcn';

    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import IconBadge from '@components/ui/icon-badge.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props {
        transactions: Data.TransactionListData[];
        table: TransactionListTable;
    }

    let { transactions, table }: Props = $props();

    /* ── Table state (read reactively from the table atoms) ─ */

    const columnFilters = $derived(table.atoms.columnFilters.get());
    const search = $derived((table.atoms.globalFilter.get() as string | undefined) ?? '');
    const sortKey = $derived(toSortKey(table.atoms.sorting.get()));

    const accountIds = $derived(getArrayFilter<number>(columnFilters, FILTER_COLUMNS.account_id));
    const categoryIds = $derived(getArrayFilter<number>(columnFilters, FILTER_COLUMNS.category_id));
    const types = $derived(
        getArrayFilter<App.Enums.TransactionType>(columnFilters, FILTER_COLUMNS.type)
    );

    /* ── State ───────────────────────────────────────────── */

    // Sheet
    type SheetKind = 'account' | 'category' | 'type' | 'sort';

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

    let sheetOpen = $state(false);
    let sheetKind = $state<SheetKind | null>(null);
    let sheetTitle = $state('');

    // Temp filters (while sheet is open)
    let tAccountIds: number[] = $state([]);
    let tCategoryIds: number[] = $state([]);
    let tTypes: App.Enums.TransactionType[] = $state([]);
    let tSort: SortKey = $state('newest');

    /* ── Derived options ─────────────────────────────────── */

    const uniqueAccounts = $derived<
        { id: number; name: string; decorations?: App.Models.Account['decorations'] }[]
    >([
        ...new Map(
            transactions.flatMap((t) => (t.account ? [[t.account.id, t.account]] : []))
        ).values(),
    ]);

    const uniqueCategories = $derived<
        { id: number; name: string; decorations?: App.Models.Category['decorations'] }[]
    >([
        ...new Map(
            transactions.flatMap((t) => (t.category ? [[t.category.id, t.category]] : []))
        ).values(),
    ]);

    /* ── Table mutations ─────────────────────────────────── */

    // An empty array must unset the filter, otherwise the in-array filter fn
    // would reject every row.
    function setArrayFilter(columnId: string, ids: unknown[]) {
        table.getColumn(columnId)?.setFilterValue(ids.length ? [...ids] : undefined);
    }

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
        type: 'Transaction Type',
        sort: 'Sort',
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
    function decorationBadgeColors(color?: string): { background: string; color: string } {
        const hex = color ? getDecorationColor(color)?.hex : undefined;

        return {
            background: hex ? `${hex}20` : 'var(--color-base-300)',
            color: hex ?? 'color-mix(in oklab, var(--color-base-content) 60%, transparent)',
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
        {
            kind: 'type',
            label:
                types.length === 0
                    ? 'All Types'
                    : types.length === 1
                      ? TYPE_STYLE[types[0]].label
                      : `${types.length} Types`,
            active: types.length > 0,
        },
    ]);

    /* ── Sheet content ───────────────────────────────────── */

    /** Normalized option list for the active sheet — the markup stays generic. */
    const sheetOptions = $derived.by<SheetOption[]>(() => {
        switch (sheetKind) {
            case 'account':
                return [
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
                            icon: 'solar--banknote-2-bold-duotone',
                            ...decorationBadgeColors(acct.decorations?.color),
                        },
                    })),
                ];
            case 'category':
                return [
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
                            icon: 'solar--tag-bold-duotone',
                            ...decorationBadgeColors(cat.decorations?.color),
                        },
                    })),
                ];
            case 'type':
                return Object.entries(TYPE_STYLE).map(([key, cfg]): SheetOption => {
                    const type = key as App.Enums.TransactionType;

                    return {
                        key,
                        label: cfg.label,
                        selected: tTypes.includes(type),
                        onSelect: () => (tTypes = toggled(tTypes, type)),
                        badge: {
                            text: cfg.label.charAt(0),
                            background: cfg.bg,
                            color: cfg.color,
                        },
                    };
                });
            case 'sort':
                return sortOptions.map((opt): SheetOption => ({
                    key: opt.id,
                    label: opt.label,
                    selected: tSort === opt.id,
                    onSelect: () => (tSort = opt.id),
                }));
            default:
                return [];
        }
    });
</script>

<div class="flex flex-col gap-3">
    <!-- ── SEARCH ───────────────────────────────────────────── -->
    <div class="flex items-center gap-2 rounded-xl bg-base-100 px-3.5 py-2.5 shadow-xs">
        <i class="iconify size-4 shrink-0 text-base-content/40 solar--magnifer-line-duotone"></i>
        <input
            class="flex-1 bg-transparent text-sm text-base-content outline-none placeholder:text-base-content/40"
            oninput={(e) => table.setGlobalFilter(e.currentTarget.value || undefined)}
            placeholder="Search transactions"
            type="text"
            value={search} />
        {#if search}
            <button
                class="flex items-center justify-center text-base-content/40 hover:text-base-content"
                aria-label="Clear search"
                onclick={() => table.setGlobalFilter(undefined)}>
                <i class="iconify size-4 solar--close-line-duotone"></i>
            </button>
        {/if}
    </div>

    <!-- ── FILTER CHIPS ─────────────────────────────────────── -->
    <div class="flex items-center gap-2">
        <div class="flex flex-1 scrollbar-none gap-1.5 overflow-x-auto">
            {#each filterChips as { kind, label, active }, i (i)}
                <button
                    class={cn(
                        'border-1.5 inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-sans text-xs font-semibold whitespace-nowrap transition-all duration-150',
                        active
                            ? 'border-primary bg-primary text-primary-content'
                            : 'border-base-content/10 bg-base-100 text-base-content/60 hover:border-primary hover:text-primary'
                    )}
                    onclick={() => openSheet(kind)}>
                    <span>{label}</span>
                    <i class="iconify size-2.5 solar--alt-arrow-down-line-duotone"></i>
                </button>
            {/each}
        </div>

        <!-- Sort button (standalone) -->
        <button
            class={cn(
                'border-1.5 flex size-9 shrink-0 items-center justify-center rounded-xl transition-all duration-150',
                sortKey !== 'newest'
                    ? 'border-primary bg-primary text-primary-content'
                    : 'border-base-content/10 bg-base-100 text-base-content/60 hover:border-primary'
            )}
            aria-label="Sort"
            onclick={() => openSheet('sort')}>
            <i class="iconify size-3.5 solar--sort-vertical-line-duotone"></i>
        </button>
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
            <button
                class="btn btn-block rounded-2xl font-bold normal-case btn-primary"
                onclick={applyFilter}>
                Apply Filters
            </button>
        {/snippet}

        {#each sheetOptions as option (option.key)}
            <button
                class={cn(
                    'flex w-full items-center gap-3 px-5 py-3 text-left font-sans transition-colors duration-100',
                    option.selected ? 'bg-info/10' : 'hover:bg-base-200/60'
                )}
                onclick={option.onSelect}>
                {@render optionBadge(option.badge)}

                <div class="flex-1 text-sm font-medium text-base-content">{option.label}</div>

                {#if option.selected}
                    <i class="iconify size-5 shrink-0 text-info solar--check-read-line-duotone"></i>
                {/if}
            </button>
        {/each}
    </DrawerModal>
</div>

{#snippet optionBadge(badge)}
    {#if badge}
        <IconBadge
            class="size-8 rounded"
            iconClass="size-5"
            background={badge.background}
            color={badge.color}
            icon={badge.icon}
            text={badge.text} />
    {/if}
{/snippet}
