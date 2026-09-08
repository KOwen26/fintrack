<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    export interface TransactionListFilters {
        search: string;
        accountIds: number[];
        categoryIds: number[];
        types: App.Enums.TransactionType[];
        sort: string; // 'newest' | 'oldest' | 'highest' | 'lowest'
    }
</script>

<script lang="ts">
    import { TYPE_STYLE } from './transaction-list-item.svelte';

    import { getDecorationColor } from '@data/decoration-colors';

    import { cn } from '@utilities/shadcn';

    import DrawerModal from '@components/ui/drawer-modal.svelte';

    /* ── Props ───────────────────────────────────────────── */

    interface Props {
        transactions: App.Models.Transaction[];
        filters: TransactionListFilters;
    }

    let { transactions, filters = $bindable() }: Props = $props();

    /* ── State ───────────────────────────────────────────── */

    // Sheet
    let sheetOpen = $state(false);
    let sheetKind = $state<string | null>(null);
    let sheetTitle = $state('');

    // Temp filters (while sheet is open)
    let tAccountIds: number[] = $state([]);
    let tCategoryIds: number[] = $state([]);
    let tTypes: App.Enums.TransactionType[] = $state([]);
    let tSort: string = $state('newest');

    /* ── Derived options ─────────────────────────────────── */

    const uniqueAccounts = $derived<
        { id: number; name: string; decorations?: App.Models.Account['decorations'] }[]
    >([...new Map(transactions.map((t) => [t.account.id, t.account])).values()] as any);

    const uniqueCategories = $derived<
        { id: number; name: string; decorations?: App.Models.Category['decorations'] }[]
    >([...new Map(transactions.map((t) => [t.category.id, t.category])).values()] as any);

    /* ── Active filter tags ──────────────────────────────── */

    const activeFilters = $derived.by(() => {
        const tags: { label: string; icon?: string; onClear: () => void }[] = [];

        for (const id of filters.accountIds) {
            const a = uniqueAccounts.find((x) => x.id === id);
            if (a)
                tags.push({
                    label: a.name,
                    onClear: () => {
                        filters.accountIds = filters.accountIds.filter((x) => x !== id);
                    },
                });
        }
        for (const id of filters.categoryIds) {
            const c = uniqueCategories.find((x) => x.id === id);
            if (c)
                tags.push({
                    label: c.name,
                    onClear: () => {
                        filters.categoryIds = filters.categoryIds.filter((x) => x !== id);
                    },
                });
        }
        for (const t of filters.types) {
            tags.push({
                label: TYPE_STYLE[t].label,
                onClear: () => {
                    filters.types = filters.types.filter((x) => x !== t);
                },
            });
        }
        if (filters.sort !== 'newest') {
            const lbl =
                filters.sort === 'oldest'
                    ? 'Terlama'
                    : filters.sort === 'highest'
                      ? 'Terbesar'
                      : 'Terkecil';
            tags.push({
                label: lbl,
                icon: 'solar--sort-vertical-line-duotone',
                onClear: () => {
                    filters.sort = 'newest';
                },
            });
        }

        return tags;
    });

    /* ── Sheet helpers ───────────────────────────────────── */

    function openSheet(kind: string) {
        tAccountIds = [...filters.accountIds];
        tCategoryIds = [...filters.categoryIds];
        tTypes = [...filters.types];
        tSort = filters.sort;

        const titles: Record<string, string> = {
            account: 'Akun',
            category: 'Kategori',
            type: 'Tipe Transaksi',
            sort: 'Urutkan',
        };
        sheetTitle = titles[kind] ?? 'Filter';
        sheetKind = kind;
        sheetOpen = true;
    }

    function closeSheet() {
        sheetOpen = false;
        sheetKind = null;
    }

    function applyFilter() {
        filters.accountIds = tAccountIds;
        filters.categoryIds = tCategoryIds;
        filters.types = tTypes;
        filters.sort = tSort;
        closeSheet();
    }

    /* ── Sheet content builders ──────────────────────────── */

    function toggleAccount(id: number) {
        const i = tAccountIds.indexOf(id);
        if (i >= 0) tAccountIds.splice(i, 1);
        else tAccountIds.push(id);
        tAccountIds = [...tAccountIds]; // trigger reactivity
    }

    function selectAllAccounts() {
        tAccountIds = [];
    }

    function toggleCategory(id: number) {
        const i = tCategoryIds.indexOf(id);
        if (i >= 0) tCategoryIds.splice(i, 1);
        else tCategoryIds.push(id);
        tCategoryIds = [...tCategoryIds];
    }

    function toggleType(t: App.Enums.TransactionType) {
        const i = tTypes.indexOf(t);
        if (i >= 0) tTypes.splice(i, 1);
        else tTypes.push(t);
        tTypes = [...tTypes];
    }

    function selectAllTypes() {
        tTypes = [];
    }

    /* ── Chip data ─────────────────────────────────────────── */

    const filterChips = $derived<{ kind: string; label: string; active: boolean }[]>([
        {
            kind: 'account',
            label:
                filters.accountIds.length === 0
                    ? 'Semua Akun'
                    : filters.accountIds.length === 1
                      ? (uniqueAccounts.find((a) => a.id === filters.accountIds[0])?.name ??
                        '1 Akun')
                      : `${filters.accountIds.length} Akun`,
            active: filters.accountIds.length > 0,
        },
        {
            kind: 'category',
            label:
                filters.categoryIds.length === 0
                    ? 'Semua Kategori'
                    : filters.categoryIds.length === 1
                      ? (uniqueCategories.find((c) => c.id === filters.categoryIds[0])?.name ??
                        '1 Kategori')
                      : `${filters.categoryIds.length} Kategori`,
            active: filters.categoryIds.length > 0,
        },
        {
            kind: 'type',
            label:
                filters.types.length === 0
                    ? 'Semua Tipe'
                    : filters.types.length === 1
                      ? TYPE_STYLE[filters.types[0]].label
                      : `${filters.types.length} Tipe`,
            active: filters.types.length > 0,
        },
        {
            kind: 'sort',
            label:
                filters.sort === 'newest'
                    ? 'Terbaru'
                    : filters.sort === 'oldest'
                      ? 'Terlama'
                      : filters.sort === 'highest'
                        ? 'Terbesar'
                        : 'Terkecil',
            active: filters.sort !== 'newest',
        },
    ]);
</script>

<div class="flex flex-col gap-3">
    <!-- ── SEARCH ───────────────────────────────────────────── -->
    <div class="flex items-center gap-2 rounded-xl bg-base-100 px-3.5 py-2.5 shadow-xs">
        <svg
            class="size-4 shrink-0 text-base-content/40"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="2.2"
            viewBox="0 0 24 24">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" x2="16.65" y1="21" y2="16.65" />
        </svg>
        <input
            class="flex-1 bg-transparent text-sm text-base-content outline-none placeholder:text-base-content/40"
            placeholder="Cari transaksi atau merchant…"
            type="text"
            bind:value={filters.search} />
        {#if filters.search}
            <button
                class="flex items-center justify-center text-base-content/40 hover:text-base-content"
                aria-label="Bersihkan pencarian"
                onclick={() => (filters.search = '')}>
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
                filters.sort !== 'newest'
                    ? 'border-primary bg-primary text-primary-content'
                    : 'border-base-content/10 bg-base-100 text-base-content/60 hover:border-primary'
            )}
            aria-label="Urutkan"
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
        contentClass="mx-auto w-full max-w-md rounded-t-2xl"
        title={sheetTitle}
        bind:open={sheetOpen}>
        {#snippet actionButton()}
            <button
                class="btn btn-block rounded-2xl font-bold normal-case btn-primary"
                onclick={applyFilter}>
                Terapkan Filter
            </button>
        {/snippet}

        {#if sheetKind === 'account'}
            <!-- Account filter -->
            <button
                class={cn(
                    'flex w-full items-center gap-3 px-5 py-3 text-left font-sans transition-colors duration-100',
                    tAccountIds.length === 0 ? 'bg-info/10' : 'hover:bg-base-200/60'
                )}
                onclick={selectAllAccounts}>
                <div
                    class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xs font-bold text-primary">
                    ALL
                </div>
                <div class="flex-1">
                    <div class="text-sm font-semibold text-base-content">Semua Akun</div>
                </div>
                <div
                    class={cn(
                        'flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition-all',
                        tAccountIds.length === 0
                            ? 'border-info bg-info text-white'
                            : 'border-base-content/20'
                    )}>
                    {#if tAccountIds.length === 0}
                        <i class="iconify text-[10px] text-white solar--check-circle-bold-duotone"
                        ></i>
                    {/if}
                </div>
            </button>
            <div class="mx-5 border-t border-base-content/10"></div>
            {#each uniqueAccounts as acct, i (i)}
                {@const selected = tAccountIds.includes(acct.id)}
                <button
                    class={cn(
                        'flex w-full items-center gap-3 px-5 py-3 text-left font-sans transition-colors duration-100',
                        selected ? 'bg-info/10' : 'hover:bg-base-200/60'
                    )}
                    onclick={() => toggleAccount(acct.id)}>
                    <div
                        style:background={acct.decorations?.color
                            ? getDecorationColor(acct.decorations.color)?.value + '20'
                            : 'var(--color-base-300)'}
                        style:color={acct.decorations?.color
                            ? getDecorationColor(acct.decorations.color)?.value
                            : 'var(--color-base-content/60)'}
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl text-base">
                        <i class="iconify size-4 solar--banknote-2-bold-duotone"></i>
                    </div>
                    <div class="flex-1">
                        <div class="text-sm font-semibold text-base-content">
                            {acct.name}
                        </div>
                    </div>
                    <div
                        class={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition-all',
                            selected ? 'border-info bg-info text-white' : 'border-base-content/20'
                        )}>
                        {#if selected}
                            <i
                                class="iconify text-[10px] text-white solar--check-circle-bold-duotone"
                            ></i>
                        {/if}
                    </div>
                </button>
            {/each}
        {:else if sheetKind === 'category'}
            <!-- Category filter -->
            <div class="flex items-center justify-between px-5 pt-3 pb-1">
                <span
                    class="text-[0.65rem] font-bold tracking-widest text-base-content/40 uppercase">
                    {tCategoryIds.length > 0 ? `${tCategoryIds.length} dipilih` : 'Semua kategori'}
                </span>
                {#if tCategoryIds.length > 0}
                    <button
                        class="text-xs font-semibold text-info"
                        onclick={() => (tCategoryIds = [])}>
                        Reset
                    </button>
                {/if}
            </div>
            <div class="grid grid-cols-3 gap-2 px-5 py-3">
                {#each uniqueCategories as cat, i (i)}
                    {@const selected = tCategoryIds.includes(cat.id)}
                    <button
                        class={cn(
                            'border-1.5 flex flex-col items-center gap-1.5 rounded-2xl px-2 py-3 font-sans transition-all duration-140',
                            selected
                                ? 'border-info bg-info/10'
                                : 'border-base-content/10 bg-transparent hover:border-primary'
                        )}
                        onclick={() => toggleCategory(cat.id)}>
                        <div
                            style:color={cat.decorations?.color
                                ? getDecorationColor(cat.decorations.color)?.value
                                : undefined}
                            class="text-lg">
                            <i class="iconify size-5 solar--tag-bold-duotone"></i>
                        </div>
                        <span
                            class={cn(
                                'text-center text-[0.67rem] leading-tight font-semibold',
                                selected ? 'text-info' : 'text-base-content/60'
                            )}>
                            {cat.name}
                        </span>
                    </button>
                {/each}
            </div>
        {:else if sheetKind === 'type'}
            <!-- Type filter -->
            <button
                class={cn(
                    'flex w-full items-center justify-between px-5 py-3 text-left font-sans transition-colors duration-100',
                    tTypes.length === 0 ? 'bg-info/10' : 'hover:bg-base-200/60'
                )}
                onclick={selectAllTypes}>
                <span class="flex items-center gap-2.5 text-sm font-medium text-base-content">
                    Semua Tipe
                </span>
                <div
                    class={cn(
                        'flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-all',
                        tTypes.length === 0 ? 'border-info bg-info' : 'border-base-content/20'
                    )}>
                    {#if tTypes.length === 0}
                        <span class="size-2 rounded-full bg-white"></span>
                    {/if}
                </div>
            </button>
            <div class="mx-5 border-t border-base-content/10"></div>
            {#each Object.entries(TYPE_STYLE) as [key, cfg], i (i)}
                {@const t = key as App.Enums.TransactionType}
                {@const selected = tTypes.includes(t)}
                <button
                    class={cn(
                        'flex w-full items-center justify-between px-5 py-3 text-left font-sans transition-colors duration-100',
                        selected ? 'bg-info/10' : 'hover:bg-base-200/60'
                    )}
                    onclick={() => toggleType(t)}>
                    <span class="flex items-center gap-2.5 text-sm font-medium text-base-content">
                        <span
                            class={cn(
                                'flex size-7 items-center justify-center rounded-lg text-xs font-bold',
                                cfg.bg,
                                cfg.color
                            )}>
                            {cfg.label.charAt(0)}
                        </span>
                        {cfg.label}
                    </span>
                    <div
                        class={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition-all',
                            selected ? 'border-info bg-info text-white' : 'border-base-content/20'
                        )}>
                        {#if selected}
                            <i
                                class="iconify text-[10px] text-white solar--check-circle-bold-duotone"
                            ></i>
                        {/if}
                    </div>
                </button>
            {/each}
        {:else if sheetKind === 'sort'}
            <!-- Sort options -->
            {#each [{ id: 'newest', label: 'Terbaru dulu' }, { id: 'oldest', label: 'Terlama dulu' }, { id: 'highest', label: 'Nominal terbesar' }, { id: 'lowest', label: 'Nominal terkecil' }] as opt, i (i)}
                <button
                    class={cn(
                        'flex w-full items-center justify-between px-5 py-3 text-left font-sans transition-colors duration-100',
                        tSort === opt.id ? 'bg-info/10' : 'hover:bg-base-200/60'
                    )}
                    onclick={() => (tSort = opt.id)}>
                    <span class="text-sm font-medium text-base-content">{opt.label}</span>
                    <div
                        class={cn(
                            'flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-all',
                            tSort === opt.id ? 'border-info bg-info' : 'border-base-content/20'
                        )}>
                        {#if tSort === opt.id}
                            <span class="size-2 rounded-full bg-white"></span>
                        {/if}
                    </div>
                </button>
            {/each}
        {/if}
    </DrawerModal>
</div>
