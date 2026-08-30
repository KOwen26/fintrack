<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { Link } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { SvelteMap } from 'svelte/reactivity';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    /* ── Props ───────────────────────────────────────────── */

    interface Props extends RestProps {
        transactions: App.Models.Transaction[];
        class?: string;
    }

    let { transactions, class: _class }: Props = $props();

    /* ── Type helpers ────────────────────────────────────── */

    const TYPE_STYLE: Record<
        App.Enums.TransactionType,
        { label: string; color: string; bg: string; sign: string }
    > = {
        [TransactionType.Income]: {
            label: 'Pemasukan',
            color: 'text-success',
            bg: 'bg-success/12',
            sign: '+',
        },
        [TransactionType.Expense]: {
            label: 'Pengeluaran',
            color: 'text-error',
            bg: 'bg-error/12',
            sign: '−',
        },
        [TransactionType.TransferOut]: {
            label: 'Transfer Keluar',
            color: 'text-warning',
            bg: 'bg-warning/12',
            sign: '↔ ',
        },
        [TransactionType.TransferIn]: {
            label: 'Transfer Masuk',
            color: 'text-info',
            bg: 'bg-info/12',
            sign: '+',
        },
        [TransactionType.Fee]: {
            label: 'Biaya',
            color: 'text-secondary',
            bg: 'bg-secondary/12',
            sign: '−',
        },
    };

    function style(t: App.Models.Transaction) {
        return TYPE_STYLE[t.type];
    }

    /* ── Date helpers ────────────────────────────────────── */

    const DD = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
    const MM = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function fmtDay(ds: string) {
        const d = new Date(ds + 'T00:00:00');

        return `${DD[d.getDay()]}, ${d.getDate()} ${MM[d.getMonth()]}`;
    }

    /* ── State ───────────────────────────────────────────── */

    // Real filters (applied)
    let searchQuery = $state('');
    let selectedAccountIds = $state<number[]>([]);
    let selectedCategoryIds = $state<number[]>([]);
    let selectedTypes = $state<App.Enums.TransactionType[]>([]);
    let sortOrder = $state('newest');

    // Sheet
    let sheetOpen = $state(false);
    let sheetKind = $state<string | null>(null);
    let sheetTitle = $state('');

    // Temp filters (while sheet is open)
    let tAccountIds: number[] = $state([]);
    let tCategoryIds: number[] = $state([]);
    let tTypes: App.Enums.TransactionType[] = $state([]);
    let tSort: string = $state('newest');

    // Load more
    let allLoaded = $state(false);

    // Dialog ref
    let dialogEl: HTMLDialogElement | undefined = $state();

    $effect(() => {
        if (sheetOpen) {
            dialogEl?.showModal();
        } else {
            dialogEl?.close();
        }
    });

    /* ── Derived options ─────────────────────────────────── */

    const uniqueAccounts = $derived<
        { id: number; name: string; decorations?: App.Models.Account['decorations'] }[]
    >([...new Map(transactions.map((t) => [t.account.id, t.account])).values()] as any);

    const uniqueCategories = $derived<
        { id: number; name: string; decorations?: App.Models.Category['decorations'] }[]
    >([...new Map(transactions.map((t) => [t.category.id, t.category])).values()] as any);

    /* ── Derived filtered list ───────────────────────────── */

    const filteredTransactions = $derived.by<App.Models.Transaction[]>(() => {
        let list = [...transactions];

        const q = searchQuery.trim().toLowerCase();
        if (q) {
            list = list.filter(
                (t) =>
                    t.description.toLowerCase().includes(q) ||
                    t.category.name.toLowerCase().includes(q) ||
                    t.account.name.toLowerCase().includes(q)
            );
        }

        if (selectedAccountIds.length) {
            list = list.filter((t) => selectedAccountIds.includes(t.account.id));
        }
        if (selectedCategoryIds.length) {
            list = list.filter((t) => selectedCategoryIds.includes(t.category.id));
        }
        if (selectedTypes.length) {
            list = list.filter((t) => selectedTypes.includes(t.type));
        }

        list.sort((a, b) => {
            if (sortOrder === 'newest')
                return b.transaction_date.localeCompare(a.transaction_date) || b.id - a.id;
            if (sortOrder === 'oldest')
                return a.transaction_date.localeCompare(b.transaction_date) || a.id - b.id;
            if (sortOrder === 'highest') return b.amount - a.amount;
            if (sortOrder === 'lowest') return a.amount - b.amount;

            return 0;
        });

        return list;
    });

    /* ── Group by date ───────────────────────────────────── */

    const groupedTransactions = $derived.by<[string, App.Models.Transaction[], number][]>(() => {
        const map = new SvelteMap<string, App.Models.Transaction[]>();
        for (const t of filteredTransactions) {
            const g = map.get(t.transaction_date);
            if (g) g.push(t);
            else map.set(t.transaction_date, [t]);
        }
        const dates = [...map.keys()];
        if (sortOrder === 'oldest') dates.sort();
        else dates.sort().reverse();

        return dates.map((d) => {
            const txns = map.get(d)!;
            const net = txns.reduce(
                (s, t) =>
                    t.type === TransactionType.Income || t.type === TransactionType.TransferIn
                        ? s + t.amount
                        : t.type === TransactionType.Expense || t.type === TransactionType.Fee
                          ? s - t.amount
                          : s,
                0
            );

            return [d, txns, net];
        });
    });

    /* ── Summary ─────────────────────────────────────────── */

    const summary = $derived.by(() => {
        let inc = 0,
            exp = 0;
        for (const t of filteredTransactions) {
            if (t.type === TransactionType.Income || t.type === TransactionType.TransferIn)
                inc += t.amount;
            else if (t.type === TransactionType.Expense || t.type === TransactionType.Fee)
                exp += t.amount;
        }
        const net = inc - exp;

        return { income: inc, expense: exp, net };
    });

    /* ── Active filter tags ──────────────────────────────── */

    const activeFilters = $derived.by(() => {
        const tags: { label: string; onClear: () => void }[] = [];

        for (const id of selectedAccountIds) {
            const a = uniqueAccounts.find((x) => x.id === id);
            if (a)
                tags.push({
                    label: a.name,
                    onClear: () => {
                        selectedAccountIds = selectedAccountIds.filter((x) => x !== id);
                    },
                });
        }
        for (const id of selectedCategoryIds) {
            const c = uniqueCategories.find((x) => x.id === id);
            if (c)
                tags.push({
                    label: c.name,
                    onClear: () => {
                        selectedCategoryIds = selectedCategoryIds.filter((x) => x !== id);
                    },
                });
        }
        for (const t of selectedTypes) {
            tags.push({
                label: TYPE_STYLE[t].label,
                onClear: () => {
                    selectedTypes = selectedTypes.filter((x) => x !== t);
                },
            });
        }
        if (sortOrder !== 'newest') {
            const lbl =
                sortOrder === 'oldest'
                    ? 'Terlama'
                    : sortOrder === 'highest'
                      ? 'Terbesar'
                      : 'Terkecil';
            tags.push({
                label: '↕ ' + lbl,
                onClear: () => {
                    sortOrder = 'newest';
                },
            });
        }

        return tags;
    });

    /* ── Sheet helpers ───────────────────────────────────── */

    function openSheet(kind: string) {
        tAccountIds = [...selectedAccountIds];
        tCategoryIds = [...selectedCategoryIds];
        tTypes = [...selectedTypes];
        tSort = sortOrder;

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
        selectedAccountIds = tAccountIds;
        selectedCategoryIds = tCategoryIds;
        selectedTypes = tTypes;
        sortOrder = tSort;
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
                selectedAccountIds.length === 0
                    ? 'Semua Akun'
                    : selectedAccountIds.length === 1
                      ? (uniqueAccounts.find((a) => a.id === selectedAccountIds[0])?.name ??
                        '1 Akun')
                      : `${selectedAccountIds.length} Akun`,
            active: selectedAccountIds.length > 0,
        },
        {
            kind: 'category',
            label:
                selectedCategoryIds.length === 0
                    ? 'Semua Kategori'
                    : selectedCategoryIds.length === 1
                      ? (uniqueCategories.find((c) => c.id === selectedCategoryIds[0])?.name ??
                        '1 Kategori')
                      : `${selectedCategoryIds.length} Kategori`,
            active: selectedCategoryIds.length > 0,
        },
        {
            kind: 'type',
            label:
                selectedTypes.length === 0
                    ? 'Semua Tipe'
                    : selectedTypes.length === 1
                      ? TYPE_STYLE[selectedTypes[0]].label
                      : `${selectedTypes.length} Tipe`,
            active: selectedTypes.length > 0,
        },
        {
            kind: 'sort',
            label:
                sortOrder === 'newest'
                    ? 'Terbaru'
                    : sortOrder === 'oldest'
                      ? 'Terlama'
                      : sortOrder === 'highest'
                        ? 'Terbesar'
                        : 'Terkecil',
            active: sortOrder !== 'newest',
        },
    ]);

    /* ── Reset ───────────────────────────────────────────── */

    function resetAllFilters() {
        searchQuery = '';
        selectedAccountIds = [];
        selectedCategoryIds = [];
        selectedTypes = [];
        sortOrder = 'newest';
    }

    /* ── Load more ───────────────────────────────────────── */

    function loadMore() {
        allLoaded = true;
    }
</script>

<div class={cn('flex flex-col gap-3', _class)}>
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
            bind:value={searchQuery} />
        {#if searchQuery}
            <button
                class="text-lg leading-none text-base-content/40 hover:text-base-content"
                onclick={() => (searchQuery = '')}>
                &times;
            </button>
        {/if}
    </div>

    <!-- ── FILTER CHIPS ─────────────────────────────────────── -->
    <div class="flex items-center gap-2">
        <div class="flex flex-1 gap-1.5 overflow-x-auto scrollbar-none">
            {#each filterChips as { kind, label, active }, i (i)}
                <button
                    class={cn(
                        'inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full border-1.5 px-3 py-1.5 font-sans text-xs font-semibold transition-all duration-150',
                        active
                            ? 'border-primary bg-primary text-primary-content'
                            : 'border-base-content/10 bg-base-100 text-base-content/60 hover:border-primary hover:text-primary'
                    )}
                    onclick={() => openSheet(kind)}>
                    <span>{label}</span>
                    <svg
                        class="size-2.5"
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="3"
                        viewBox="0 0 24 24">
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </button>
            {/each}
        </div>

        <!-- Sort button (standalone) -->
        <button
            class={cn(
                'flex size-9 shrink-0 items-center justify-center rounded-xl border-1.5 transition-all duration-150',
                sortOrder !== 'newest'
                    ? 'border-primary bg-primary text-primary-content'
                    : 'border-base-content/10 bg-base-100 text-base-content/60 hover:border-primary'
            )}
            aria-label="Urutkan"
            onclick={() => openSheet('sort')}>
            <svg
                class="size-3.5"
                fill="none"
                stroke="currentColor"
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2.5"
                viewBox="0 0 24 24">
                <line x1="3" x2="21" y1="6" y2="6" />
                <line x1="3" x2="15" y1="12" y2="12" />
                <line x1="3" x2="10" y1="18" y2="18" />
            </svg>
        </button>
    </div>

    <!-- ── ACTIVE FILTER TAGS ──────────────────────────────── -->
    {#if activeFilters.length > 0}
        <div class="flex flex-wrap gap-1.5">
            {#each activeFilters as tag, i (i)}
                <button
                    class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 font-sans text-xs font-semibold text-primary transition-all duration-120 hover:bg-primary/20"
                    onclick={tag.onClear}>
                    <span>{tag.label}</span>
                    <span class="text-sm leading-none">&times;</span>
                </button>
            {/each}
        </div>
    {/if}

    <!-- ── SUMMARY STRIP ───────────────────────────────────── -->
    {#if transactions.length > 0}
        <div class="overflow-hidden rounded-2xl bg-base-100 shadow-xs">
            <div class="flex">
                <div class="flex-1 space-y-1 px-4 py-3.5">
                    <div
                        class="flex items-center gap-1 text-[0.64rem] font-bold uppercase tracking-wider text-base-content/40">
                        <span class="text-success">&#8593;</span> Masuk
                    </div>
                    <div class="font-mono text-sm font-medium text-success">
                        {summary.income > 0 ? Formatter.currency(summary.income) : '\u2014'}
                    </div>
                </div>
                <div class="flex-1 space-y-1 border-l border-base-content/10 px-4 py-3.5">
                    <div
                        class="flex items-center gap-1 text-[0.64rem] font-bold uppercase tracking-wider text-base-content/40">
                        <span class="text-error">&#8595;</span> Keluar
                    </div>
                    <div class="font-mono text-sm font-medium text-error">
                        {summary.expense > 0 ? Formatter.currency(summary.expense) : '\u2014'}
                    </div>
                </div>
                <div class="flex-1 space-y-1 border-l border-base-content/10 px-4 py-3.5">
                    <div
                        class="text-[0.64rem] font-bold uppercase tracking-wider text-base-content/40">
                        &#8645; Net
                    </div>
                    <div
                        class="font-mono text-sm font-medium"
                        class:font-medium={true}
                        class:text-error={summary.net < 0}
                        class:text-success={summary.net >= 0}>
                        {summary.net !== 0
                            ? (summary.net > 0 ? '+' : '\u2212') +
                              Formatter.currency(Math.abs(summary.net))
                            : '\u2014'}
                    </div>
                </div>
            </div>
        </div>
    {/if}

    <!-- ── TRANSACTION LIST ────────────────────────────────── -->
    {#if filteredTransactions.length === 0}
        <!-- Empty state -->
        <div class="px-6 py-14 text-center text-base-content/40">
            <div class="mb-3 text-4xl">&#128269;</div>
            <p class="mb-1 text-sm font-bold text-base-content/60">Tidak ada transaksi</p>
            <p class="text-xs leading-relaxed">
                Tidak ada transaksi yang cocok<br />
                dengan filter yang dipilih.
            </p>
            {#if activeFilters.length > 0 || searchQuery}
                <button
                    class="btn btn-primary btn-sm mt-4 rounded-full px-5 font-semibold normal-case"
                    onclick={resetAllFilters}>
                    Reset semua filter
                </button>
            {:else}
                <Link
                    class="btn btn-primary btn-sm mt-4 rounded-full px-5 font-semibold normal-case"
                    href={TransactionController.create.url()}>
                    Tambah transaksi
                </Link>
            {/if}
        </div>
    {:else}
        <div class="flex flex-col">
            {#each groupedTransactions as [date, txns, dayNet], i (i)}
                <!-- Day header -->
                <div class="group mb-2">
                    <div class="flex items-center justify-between px-1 pb-2">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold text-base-content/60"
                                >{fmtDay(date)}</span>
                            <span
                                class="rounded-full bg-base-content/10 px-2 py-0.5 text-[0.65rem] font-semibold text-base-content/40">
                                {txns.length} transaksi
                            </span>
                        </div>
                        {#if dayNet !== 0}
                            <span
                                class="font-mono text-xs font-medium"
                                class:text-error={dayNet < 0}
                                class:text-success={dayNet > 0}>
                                {dayNet > 0 ? '+' : '\u2212'}
                                {Formatter.currency(Math.abs(dayNet))}
                            </span>
                        {/if}
                    </div>

                    <!-- Group card -->
                    <div class="overflow-hidden rounded-2xl bg-base-100 shadow-xs">
                        {#each txns as txn (txn.id)}
                            {@const s = style(txn)}
                            <Link
                                class="flex items-center gap-3 border-b border-base-content/10 px-4 py-3 transition-colors duration-100 last:border-b-0 hover:bg-base-200/40"
                                href={TransactionController.show.url(txn)}>
                                <div
                                    class={cn(
                                        'flex size-10 shrink-0 items-center justify-center rounded-xl',
                                        s.bg
                                    )}>
                                    <i
                                        class={cn(
                                            'iconify size-4',
                                            txn.type === TransactionType.Income ||
                                                txn.type === TransactionType.TransferIn
                                                ? 'ph--arrow-up-bold'
                                                : txn.type === TransactionType.Expense
                                                  ? 'ph--arrow-down-bold'
                                                  : 'ph--arrows-left-right-bold',
                                            s.color
                                        )}></i>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-semibold text-base-content">
                                        {txn.description}
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center gap-1.5 text-xs text-base-content/40">
                                        <span>{txn.category.name}</span>
                                        {#if txn.account}
                                            <span class="size-0.5 rounded-full bg-base-content/20"
                                            ></span>
                                            <span>{txn.account.name}</span>
                                        {/if}
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class={cn('font-mono text-sm font-medium', s.color)}>
                                        {s.sign}
                                        {Formatter.currency(txn.amount)}
                                    </div>
                                </div>
                            </Link>
                        {/each}
                    </div>
                </div>
            {/each}
        </div>

        <!-- Load more -->
        {#if !allLoaded}
            <button
                class="mx-0 mb-0 mt-2 flex w-full items-center justify-center gap-2 rounded-2xl bg-base-100 px-4 py-3.5 font-sans text-sm font-semibold text-primary shadow-xs transition-colors duration-150 hover:bg-primary/10"
                onclick={loadMore}>
                <svg
                    class="size-3.5"
                    fill="none"
                    stroke="currentColor"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2.5"
                    viewBox="0 0 24 24">
                    <polyline points="6 9 12 15 18 9" />
                </svg>
                Muat lebih banyak
            </button>
        {:else}
            <p class="py-3 text-center text-xs text-base-content/40">
                Semua transaksi sudah ditampilkan
            </p>
        {/if}
    {/if}

    <!-- ── BOTTOM SHEET ────────────────────────────────────── -->
    <dialog bind:this={dialogEl} class="modal modal-bottom" onclose={() => (sheetOpen = false)}>
        <div class="modal-box max-w-md rounded-t-2xl p-0">
            <!-- Handle indicator -->
            <div class="mx-auto mt-3 h-1 w-8 rounded-full bg-base-content/20"></div>

            <!-- Header -->
            <div
                class="flex items-center justify-between border-b border-base-content/10 px-5 pb-3 pt-4">
                <span class="text-sm font-bold text-base-content">{sheetTitle}</span>
                <button
                    class="flex size-7 items-center justify-center rounded-lg bg-base-200 text-base-content/60"
                    aria-label="Tutup"
                    onclick={closeSheet}>
                    <svg
                        class="size-3.5"
                        fill="none"
                        stroke="currentColor"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        viewBox="0 0 24 24">
                        <line x1="18" x2="6" y1="6" y2="18" />
                        <line x1="6" x2="18" y1="6" y2="18" />
                    </svg>
                </button>
            </div>

            <!-- Body -->
            <div class="max-h-[60vh] overflow-y-auto">
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
                                <span class="text-[10px] font-bold">&#10003;</span>
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
                                <i class="iconify size-4 ph--bank-bold"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-base-content">
                                    {acct.name}
                                </div>
                            </div>
                            <div
                                class={cn(
                                    'flex size-5 shrink-0 items-center justify-center rounded-md border-2 transition-all',
                                    selected
                                        ? 'border-info bg-info text-white'
                                        : 'border-base-content/20'
                                )}>
                                {#if selected}
                                    <span class="text-[10px] font-bold">&#10003;</span>
                                {/if}
                            </div>
                        </button>
                    {/each}
                {:else if sheetKind === 'category'}
                    <!-- Category filter -->
                    <div class="flex items-center justify-between px-5 pb-1 pt-3">
                        <span
                            class="text-[0.65rem] font-bold uppercase tracking-widest text-base-content/40">
                            {tCategoryIds.length > 0
                                ? `${tCategoryIds.length} dipilih`
                                : 'Semua kategori'}
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
                                    'flex flex-col items-center gap-1.5 rounded-2xl border-1.5 px-2 py-3 font-sans transition-all duration-140',
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
                                    <i class="iconify size-5 ph--tag-bold"></i>
                                </div>
                                <span
                                    class={cn(
                                        'text-center text-[0.67rem] font-semibold leading-tight',
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
                        <span
                            class="flex items-center gap-2.5 text-sm font-medium text-base-content">
                            Semua Tipe
                        </span>
                        <div
                            class={cn(
                                'flex size-5 shrink-0 items-center justify-center rounded-full border-2 transition-all',
                                tTypes.length === 0
                                    ? 'border-info bg-info'
                                    : 'border-base-content/20'
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
                            <span
                                class="flex items-center gap-2.5 text-sm font-medium text-base-content">
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
                                    selected
                                        ? 'border-info bg-info text-white'
                                        : 'border-base-content/20'
                                )}>
                                {#if selected}
                                    <span class="text-[10px] font-bold">&#10003;</span>
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
                                    tSort === opt.id
                                        ? 'border-info bg-info'
                                        : 'border-base-content/20'
                                )}>
                                {#if tSort === opt.id}
                                    <span class="size-2 rounded-full bg-white"></span>
                                {/if}
                            </div>
                        </button>
                    {/each}
                {/if}
            </div>

            <!-- Footer -->
            <div class="border-t border-base-content/10 px-5 pb-8 pt-3">
                <button
                    class="btn btn-primary btn-block rounded-2xl font-bold normal-case"
                    onclick={applyFilter}>
                    Terapkan Filter
                </button>
            </div>
        </div>
        <form class="modal-backdrop" method="dialog">
            <button onclick={closeSheet}>close</button>
        </form>
    </dialog>
</div>
