<script lang="ts">
    import type { SortKey, TransactionListHandler } from '@services/transaction-list.svelte';
    import type { App } from '@wayfinder/types';
    import type { Snippet } from 'svelte';

    import { TYPE_STYLE } from './transaction-list-item.svelte';

    import { DatePeriodPresetMeta } from '@wayfinder/App/Enums/DatePeriodPreset';

    import { cn } from '@utilities/shadcn';
    import { SORT_LABELS, SORT_OPTIONS } from '@services/transaction-list.svelte';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import PeriodPresetSelect from '@components/module/period-preset-select.svelte';
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
            decorations?: { icon?: string | null; color?: string | null } | null;
        }[];
        categories: {
            id: string;
            name: string;
            decorations?: { icon?: string | null; color?: string | null } | null;
        }[];
        list: TransactionListHandler;
    }

    let { accounts: uniqueAccounts, categories: uniqueCategories, list }: Props = $props();

    /* ── State ───────────────────────────────────────────── */

    // Sheet — `settings` groups the server-driven period, the transaction-type
    // enum and the sort selector into a single drawer, sectioned per filter.
    type SheetKind = 'account' | 'category' | 'settings';

    /**
     * Leading badge config forwarded to <DecorationBadge> — direct values
     * act as the badge's fallbacks; decoration lookups resolve inside the
     * badge and win over them.
     */
    interface RowBadge {
        icon?: string;
        text?: string;
        label?: string;
        decorations?: { icon?: string | null; color?: string | null } | null;
        background?: string;
        color?: string;
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
    type PeriodPreset = App.Enums.DatePeriodPreset;

    let tAccountIds: number[] = $state([]);
    let tCategoryIds: string[] = $state([]);
    let tTypes: App.Enums.TransactionType[] = $state([]);
    let tSort: SortKey = $state('newest');
    let tPeriod: PeriodPreset | undefined = $state(undefined);

    function handleSearchInput(value: string) {
        list.search(value);
    }

    /* ── Active filter tags ──────────────────────────────── */
    const activeFilters = $derived.by(() => {
        const tags: { label: string; icon?: string; onClear?: () => void }[] = [];
        // The period is always visible as a status tag, t behaves like a radio, never unset, only replaced from the options
        tags.push({
            label: DatePeriodPresetMeta[list.period].label,
            icon: 'solar--calendar-mark-bold-duotone',
        });

        if (list.sortKey !== 'newest') {
            tags.push({
                label: SORT_LABELS[list.sortKey],
                icon: 'solar--sort-vertical-line-duotone',
                onClear: () => {
                    list.setSort('newest');
                },
            });
        }

        for (const id of list.accountFilter) {
            const account = uniqueAccounts.find((x) => x.id === id);
            if (account)
                tags.push({
                    label: account.name,
                    onClear: () => {
                        list.setAccountFilter(list.accountFilter.filter((x) => x !== id));
                    },
                });
        }
        for (const id of list.categoryFilter) {
            const category = uniqueCategories.find((x) => x.id === id);
            if (category)
                tags.push({
                    label: category.name,
                    onClear: () => {
                        list.setCategoryFilter(list.categoryFilter.filter((x) => x !== id));
                    },
                });
        }
        for (const type of list.typeFilter) {
            tags.push({
                label: TYPE_STYLE[type].label,
                onClear: () => {
                    list.setTypeFilter(list.typeFilter.filter((x) => x !== type));
                },
            });
        }

        return tags;
    });

    let activeFiltersFold = $state(false);

    /* ── Sheet helpers ───────────────────────────────────── */

    const SHEET_TITLES: Record<SheetKind, string> = {
        account: 'Account',
        category: 'Category',
        settings: 'Settings',
    };

    function openSheet(kind: SheetKind) {
        tAccountIds = [...list.accountFilter];
        tCategoryIds = [...list.categoryFilter];
        tTypes = [...list.typeFilter];
        tSort = list.sortKey;
        tPeriod = list.paginated ? list.period : undefined;

        sheetTitle = SHEET_TITLES[kind];
        sheetKind = kind;
        sheetOpen = true;
    }

    function closeSheet() {
        sheetOpen = false;
        sheetKind = null;
    }

    function applyFilter() {
        // Period is server-side: release it before the client filters so the
        // reload carrying it lands first.
        if (list.paginated && tPeriod !== undefined && tPeriod !== list.period) {
            list.setPeriod(tPeriod);
        }

        list.setAccountFilter(tAccountIds);
        list.setCategoryFilter(tCategoryIds);
        list.setTypeFilter(tTypes);
        list.setSort(tSort);
        closeSheet();
    }

    /** Pure toggle — returns a new array with `item` added or removed. */
    function toggled<T>(items: T[], item: T): T[] {
        return items.includes(item) ? items.filter((x) => x !== item) : [...items, item];
    }

    /* ── Chip data ─────────────────────────────────────────── */

    const filterChips = $derived<{ kind: SheetKind; label: string; active: boolean }[]>([
        {
            kind: 'account',
            label:
                list.accountFilter.length === 0
                    ? 'All Accounts'
                    : list.accountFilter.length === 1
                      ? (uniqueAccounts.find((a) => a.id === list.accountFilter[0])?.name ??
                        '1 Account')
                      : `${list.accountFilter.length} Accounts`,
            active: list.accountFilter.length > 0,
        },
        {
            kind: 'category',
            label:
                list.categoryFilter.length === 0
                    ? 'All Categories'
                    : list.categoryFilter.length === 1
                      ? (uniqueCategories.find((c) => c.id === list.categoryFilter[0])?.name ??
                        '1 Category')
                      : `${list.categoryFilter.length} Categories`,
            active: list.categoryFilter.length > 0,
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
                                    label: 'ALL',
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
                                    decorations: acct.decorations,
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
                                },
                            },
                            ...uniqueCategories.map((cat): SheetOption => ({
                                key: String(cat.id),
                                label: cat.name,
                                selected: tCategoryIds.includes(cat.id),
                                onSelect: () => (tCategoryIds = toggled(tCategoryIds, cat.id)),
                                badge: {
                                    decorations: cat.decorations,
                                },
                            })),
                        ],
                    },
                ];
            case 'settings':
                return [{ label: null, content: settingContent }];
            default:
                return [];
        }
    });
</script>

<div class="flex flex-col gap-3">
    <div class="flex items-center gap-2">
        <SearchInput
            class="flex-1"
            onclear={() => {
                list.clearSearch();
            }}
            oninput={handleSearchInput}
            placeholder="Search transactions"
            value={list.searchValue} />

        <Button
            class={cn(
                (list.sortKey !== 'newest' || list.periodActive) &&
                    'bg-secondary text-secondary-foreground'
            )}
            aria-label="Sort"
            color="secondary"
            onclick={() => openSheet('settings')}
            size="icon"
            variant="outline">
            <i class="iconify size-5 solar--tuning-3-line-duotone"></i>
        </Button>
    </div>

    <!-- ── FILTER CHIPS ─────────────────────────────────────── -->
    <div class="flex items-center gap-2">
        <div class="flex flex-1 scrollbar-none gap-1.5 overflow-x-auto">
            {#each filterChips as { kind, label, active }, i (i)}
                <button
                    class={cn(
                        'border-1.5 inline-flex shrink-0 items-center gap-1.5 rounded-full px-3 py-1.5 font-sans text-xs font-semibold whitespace-nowrap transition-all duration-150',
                        active
                            ? 'border-primary bg-primary text-primary-foreground'
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
        <div
            class={[
                'flex flex-wrap gap-1.5',
                activeFiltersFold ? 'max-h-none' : 'max-h-14 overflow-hidden',
            ]}>
            {#if activeFilters.length > 5}
                <button
                    class="inline-flex size-6 shrink-0 items-center justify-center rounded-full bg-secondary/10 text-secondary transition-colors hover:bg-secondary/20"
                    aria-expanded={activeFiltersFold}
                    aria-label={activeFiltersFold
                        ? 'Show fewer filter chips'
                        : 'Show all filter chips'}
                    onclick={() => (activeFiltersFold = !activeFiltersFold)}>
                    <i
                        class={[
                            'iconify size-3 transition-transform duration-150',
                            activeFiltersFold
                                ? 'solar--alt-arrow-up-line-duotone'
                                : 'solar--alt-arrow-down-line-duotone',
                        ]}></i>
                </button>
            {/if}

            {#each activeFilters as tag, i (i)}
                <button
                    class={[
                        'inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 font-sans text-xs font-semibold whitespace-nowrap text-primary transition-all duration-120 hover:bg-primary/20',
                        !tag.onClear && 'pointer-events-none',
                    ]}
                    onclick={tag.onClear}>
                    {#if tag.icon}<i class="iconify size-3 {tag.icon}"></i>{/if}
                    <span>{tag.label}</span>
                    <i
                        class={[
                            'iconify size-3 solar--close-line-duotone',
                            !tag.onClear && 'hidden',
                        ]}></i>
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
        {#if list.paginated}
            <div class="py-2">
                {@render sectionHeading('Period')}
            </div>

            <PeriodPresetSelect
                class="w-full"
                preset="rolling"
                variant="radio"
                bind:value={tPeriod} />

            <div class="my-2 border-t border-border"></div>
        {/if}

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

        {#each SORT_OPTIONS as sortOption (sortOption.id)}
            {@render optionRow({
                key: sortOption.id,
                label: sortOption.label,
                selected: tSort === sortOption.id,
                onSelect: () => (tSort = sortOption.id),
            })}
        {/each}
    </div>
{/snippet}

{#snippet optionBadge(badge: RowBadge | undefined)}
    {#if badge}
        <DecorationBadge
            class="size-8 rounded"
            iconClass="size-5"
            background="var(--color-popover)"
            color="color-mix(in oklab, var(--color-foreground) 60%, transparent)"
            {...badge} />
    {/if}
{/snippet}
