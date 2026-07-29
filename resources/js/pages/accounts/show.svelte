<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import PageSection from '@components/layouts/page-section.svelte';
    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import StatCard from '@components/ui/cards/stat-card.svelte';
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    let { account }: { account: App.Models.Account } = $props();

    // ── Decoration-driven colors ───────────────────────────────

    const colorSlug = $derived(account.decorations?.color);
    const iconSlug = $derived(account.decorations?.icon);

    const colorObj = $derived(colorSlug ? getDecorationColor(colorSlug) : undefined);
    const iconObj = $derived(iconSlug ? getDecorationIcon(iconSlug) : undefined);

    const bgColor = $derived(colorObj?.oklch ?? 'oklch(0.45 0.08 160)');
    const heroIcon = $derived<string | undefined>(iconObj?.value);

    const providerName = $derived<string | undefined>(
        (account.provider as { name?: string } | null)?.name
    );

    // ── Balance visibility ─────────────────────────────────────

    let balanceHidden = $state(false);

    function toggleBalance(): void {
        balanceHidden = !balanceHidden;
    }

    // ── Category spending from transactions ────────────────────

    interface CategoryItem {
        name: string;
        amount: number;
        color: string;
        percentage: number;
    }

    const transactions = $derived(account.transactions ?? []);

    const categorySpending = $derived.by<CategoryItem[]>(() => {
        const groups = new SvelteMap<string, { name: string; amount: number; color: string }>();

        for (const txn of transactions) {
            const cat = txn.category;
            if (!cat) continue;

            const existing = groups.get(cat.name) ?? {
                name: cat.name,
                amount: 0,
                color: '#6b7280',
            };

            if (cat.decorations?.color) {
                const dc = getDecorationColor(cat.decorations.color);
                if (dc?.hex) existing.color = dc.hex;
            }

            existing.amount += Number(txn.amount);
            groups.set(cat.name, existing);
        }

        const items = [...groups.values()];
        const total = items.reduce((sum, i) => sum + i.amount, 0);

        return items.map((i) => ({
            ...i,
            percentage: total > 0 ? Math.round((i.amount / total) * 100) : 0,
        }));
    });

    const totalSpent = $derived(categorySpending.reduce((sum, i) => sum + i.amount, 0));

    // ── Income / expense totals for stat cards ─────────────────

    const incomeTotal = $derived(
        transactions
            .filter((t) => t.type === 'income' || t.type === 'transfer_in')
            .reduce((sum, t) => sum + Number(t.amount), 0)
    );

    const expenseTotal = $derived(
        transactions
            .filter((t) => t.type !== 'income' && t.type !== 'transfer_in')
            .reduce((sum, t) => sum + Number(t.amount), 0)
    );

    const netSavings = $derived(incomeTotal - expenseTotal);

    // ── Quick actions ──────────────────────────────────────────

    const currentMonthLabel = new Date().toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric',
    });

    interface ActionItem {
        icon: string;
        label: string;
    }

    const quickActions: ActionItem[] = [
        { icon: 'ph--arrow-up-right-bold', label: 'Transfer' },
        { icon: 'ph--plus-bold', label: 'Top Up' },
        { icon: 'ph--arrow-down-bold', label: 'Withdraw' },
        { icon: 'ph--file-text-bold', label: 'Report' },
    ];
</script>

<DashboardPageHeader title="">
    <div class="space-y-1.5">
        <h1 class="text-xl font-bold">{account.name}</h1>
        <div class="flex items-center gap-1.5">
            <AccountTypeBadge type={account.type} />
            <AccountAccessTypeBadge type={account.access_type} />
            {#if providerName}
                <Badge color="light">{providerName}</Badge>
            {/if}
        </div>
    </div>

    {#snippet actions()}
        <Button
            color="light"
            href={AccountController.edit.url({ account: account.id })}
            variant="outline">
            <i class="iconify size-4 ph--pencil-simple-bold"></i>
            Edit
        </Button>
    {/snippet}
</DashboardPageHeader>

<div class="mx-auto max-w-6xl">
    <!-- ═══ Breadcrumb — desktop only ═══ -->
    <nav class="mb-4 hidden items-center gap-1.5 text-sm text-base-content/60 md:flex">
        <a class="transition-colors hover:text-base-content" href="/">Home</a>
        <span>/</span>
        <a class="transition-colors hover:text-base-content" href={AccountController.index.url()}>
            Accounts
        </a>
        <span>/</span>
        <span class="font-medium text-base-content">{account.name}</span>
    </nav>

    <!-- ════════════════════════════════════════════ -->
    <!--  HERO CARD                                  -->
    <!-- ════════════════════════════════════════════ -->
    <div class="overflow-hidden rounded-xl shadow-xs">
        <div
            style:background={bgColor}
            style:color={colorObj?.text_color}
            class="relative overflow-hidden px-5 pt-5 pb-4 md:px-6 md:pb-5">
            <!-- Decorative circles -->
            <div
                class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-white/5">
            </div>
            <div
                class="pointer-events-none absolute right-10 -bottom-8 size-28 rounded-full bg-white/5">
            </div>

            <!-- Header row -->
            <div class="relative z-1 flex items-center gap-2">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/[0.18] md:size-9">
                    {#if heroIcon}
                        <i class="iconify size-4 text-current md:size-4.5 {heroIcon}"></i>
                    {:else}
                        <i class="iconify size-4 text-current ph--bank-bold md:size-4.5"></i>
                    {/if}
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-1.5">
                        <span class="text-sm font-semibold text-current md:text-base">
                            {account.name}
                        </span>
                        <span
                            class="rounded-full bg-white/[0.18] px-1.5 py-0.5 text-[9px] font-medium tracking-wider text-current/85 uppercase">
                            {account.type === 'debit_account'
                                ? 'Debit'
                                : account.type === 'credit_card'
                                  ? 'Credit'
                                  : account.type === 'e_wallet'
                                    ? 'E-Wallet'
                                    : account.type === 'cash_wallet'
                                      ? 'Cash'
                                      : 'Investment'}
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-current/70">
                        {#if providerName}
                            <span>{providerName}</span>
                            <span>•</span>
                        {/if}
                        <span>{account.access_type === 'personal' ? 'Personal' : 'Joint'}</span>
                    </div>
                </div>
                <button
                    class="flex size-8 cursor-pointer items-center justify-center rounded-lg border-none bg-white/10 transition-colors hover:bg-white/15"
                    aria-label="Toggle balance visibility"
                    onclick={toggleBalance}>
                    {#if balanceHidden}
                        <svg
                            fill="none"
                            height="16"
                            stroke="rgba(255,255,255,0.6)"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                            width="16">
                            <path
                                d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                            <line x1="1" x2="23" y1="1" y2="23" />
                        </svg>
                    {:else}
                        <svg
                            fill="none"
                            height="16"
                            stroke="rgba(255,255,255,0.6)"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            viewBox="0 0 24 24"
                            width="16">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    {/if}
                </button>
            </div>

            <!-- Balance -->
            <div class="relative z-1 mt-4 md:mt-5">
                <div
                    class="flex items-center gap-1.5 text-sm font-medium tracking-wider text-current/70 uppercase">
                    Current Balance
                </div>
                <p
                    class="mt-0.5 text-3xl font-bold tracking-tight text-current md:text-4xl lg:text-5xl">
                    {#if balanceHidden}
                        ••••••
                    {:else}
                        {Formatter.currency(
                            account.current_balance ?? account.initial_balance ?? 0
                        )}
                    {/if}
                </p>
                {#if incomeTotal > 0}
                    <div class="mt-1.5 flex items-center gap-1.5 text-sm">
                        <i class="iconify size-4 text-success-300 ph--trend-up-bold"></i>
                        <span class="text-current/85">
                            +{Formatter.currency(incomeTotal, true)}
                        </span>
                        <span class="text-current/55">this month</span>
                    </div>
                {/if}
            </div>

            <!-- Quick actions — horizontal scroll on mobile, static row on desktop -->
            <div
                style="scrollbar-width: none; -ms-overflow-style: none;"
                class="relative z-1 -mx-5 mt-5 overflow-x-auto px-5 pb-1 md:mx-0 md:px-0">
                <div class="flex gap-2 md:flex-wrap">
                    {#each quickActions as action (action.label)}
                        <button
                            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-lg bg-white/15 px-3.5 py-2 text-sm font-medium text-current/90 transition-colors hover:bg-white/25 md:shrink"
                            type="button">
                            <i class="iconify size-4 {action.icon}"></i>
                            {action.label}
                        </button>
                    {/each}
                </div>
            </div>
        </div>

        <!-- Detail badges row — white background -->
        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-base-300 bg-card px-5 py-3 text-xs">
            {#if account.account_number}
                <span class="flex items-center gap-1">
                    <i class="iconify size-3 text-base-content/40 ph--identification-card-bold"></i>
                    <span class="text-base-content/50">{account.account_number}</span>
                </span>
            {/if}
            {#if account.created_at}
                <span class="flex items-center gap-1">
                    <i class="iconify size-3 text-base-content/40 ph--calendar-bold"></i>
                    <span class="text-base-content/50"
                        >Since {DateTimeHelper.format(account.created_at, 'date')}</span>
                </span>
            {/if}
            <Button
                class="ml-auto text-xs"
                color="primary"
                href={AccountController.edit.url({ account: account.id })}
                variant="link">
                Edit
            </Button>
        </div>
    </div>

    <!-- ════════════════════════════════════════════ -->
    <!--  SPENDING BY CATEGORY                        -->
    <!-- ════════════════════════════════════════════ -->
    {#if categorySpending.length > 0}
        <PageSection>
            <Card class="p-5">
                {#snippet header()}
                    <h2 class="text-sm font-semibold">Spending by Category</h2>
                {/snippet}
                {#snippet headerAction()}
                    <span class="text-xs text-base-content/50">{currentMonthLabel}</span>
                {/snippet}

                <div class="flex flex-col items-center gap-5 md:flex-row md:items-start md:gap-6">
                    <div class="w-36 shrink-0 md:w-40">
                        <DonutChart
                            centerSubtext="Total spent"
                            centerText={Formatter.currency(totalSpent, true)}
                            data={categorySpending.map((c) => ({
                                name: c.name,
                                value: c.amount,
                                color: c.color,
                            }))}
                            innerRadius={0.6} />
                    </div>
                    <div class="w-full space-y-2.5 md:flex-1">
                        {#each categorySpending as item (item.name)}
                            <div class="flex items-center gap-2.5">
                                <span
                                    style:background={item.color}
                                    class="size-3 shrink-0 rounded-full">
                                </span>
                                <span class="flex-1 text-sm text-base-content">{item.name}</span>
                                <span class="text-sm font-semibold text-base-content">
                                    {Formatter.currency(item.amount, true)}
                                </span>
                                <span class="w-8 text-right text-sm text-base-content/50">
                                    {item.percentage}%
                                </span>
                            </div>
                        {/each}
                    </div>
                </div>
            </Card>
        </PageSection>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  RECENT TRANSACTIONS                         -->
    <!-- ════════════════════════════════════════════ -->
    <PageSection>
        {#if transactions.length > 0}
            <Card class="p-5">
                {#snippet header()}
                    <h2 class="text-sm font-semibold">Recent Transactions</h2>
                {/snippet}
                {#snippet headerAction()}
                    <Link
                        class="text-xs font-medium text-primary transition-colors hover:text-primary/80"
                        href="/transactions">
                        View All
                    </Link>
                {/snippet}
                <TransactionList {transactions} />
            </Card>
        {:else}
            <EmptyItemPlaceholder label="No Transaction Yet" />
        {/if}
    </PageSection>

    <!-- ════════════════════════════════════════════ -->
    <!--  DESKTOP STAT CARDS                         -->
    <!-- ════════════════════════════════════════════ -->
    <PageSection>
        <div class="hidden md:grid md:grid-cols-3 md:gap-4">
            <StatCard color="success" icon="trending-up" label="Income" value={incomeTotal} />
            <StatCard color="error" icon="trending-down" label="Expenses" value={expenseTotal} />
            <StatCard
                color="primary"
                icon="piggy-bank"
                label="Savings"
                value={netSavings >= 0 ? netSavings : 0} />
        </div>
    </PageSection>
</div>
