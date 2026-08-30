<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import PageSection from '@components/layouts/page-section.svelte';
    import AccountCard2 from '@components/module/account/account-card-2.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import StatCard from '@components/ui/cards/stat-card.svelte';
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    let { account }: { account: App.Models.Account } = $props();

    const providerName = $derived<string | undefined>(
        (account.provider as { name?: string } | null)?.name
    );

    let showDetail = $state(false);

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

    // ── Detail view helpers ────────────────────────────────────
    const colorObj = $derived(
        account.decorations?.color ? getDecorationColor(account.decorations.color) : undefined
    );
    const bgColor = $derived(colorObj?.oklch ?? 'oklch(0.45 0.08 160)');

    interface InfoRow {
        label: string;
        value: string;
        mono: boolean;
        icon: string;
    }

    const infoRows = $derived.by<InfoRow[]>(() => {
        const rows: InfoRow[] = [
            {
                icon: 'ph--identification-badge-bold',
                label: 'Account Type',
                value: account.type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
                mono: false,
            },
        ];

        if (providerName) {
            rows.push({
                icon: 'ph--building-bold',
                label: 'Provider',
                value: providerName,
                mono: false,
            });
        }

        if (account.access_type) {
            rows.push({
                icon: 'ph--users-three-bold',
                label: 'Access Type',
                value: account.access_type === 'personal' ? 'Personal' : 'Joint',
                mono: false,
            });
        }

        if (account.currency) {
            rows.push({
                icon: 'ph--currency-circle-dollar-bold',
                label: 'Currency',
                value: account.currency,
                mono: false,
            });
        }

        rows.push({
            icon: 'ph--calendar-bold',
            label: 'Created',
            value: DateTimeHelper.format(account.created_at, 'date'),
            mono: false,
        });

        return rows;
    });

    const members = $derived([
        { name: 'John Doe', email: 'john@example.com', role: 'Owner' },
        { name: 'Jane Smith', email: 'jane@example.com', role: 'Member' },
    ]);

    function getMemberInitials(name: string): string {
        return name
            .split(' ')
            .map((w) => w[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }
</script>

<DashboardPageHeader title="">
    <div>
        <div class="space-y-1.5">
            <h1 class="text-xl font-bold">{account.name}</h1>
            <div class="flex items-center gap-1.5">
                <!-- <AccountTypeBadge type={account.type} /> -->
                <!-- <AccountAccessTypeBadge type={account.access_type} /> -->
                <!-- {#if providerName}
                <Badge color="light">{providerName}</Badge>
            {/if} -->
            </div>
        </div>

        <!-- ═══ Breadcrumb — desktop only ═══ -->
        <nav class="mb-4 hidden items-center gap-1.5 text-sm text-base-content/60 md:flex">
            <a class="transition-colors hover:text-base-content" href="/">Home</a>
            <span>/</span>
            <a
                class="transition-colors hover:text-base-content"
                href={AccountController.index.url()}>
                Accounts
            </a>
        </nav>
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

<div class="space-y-5">
    <!-- ════════════════════════════════════════════ -->
    <!--  HERO CARD                                  -->
    <!-- ════════════════════════════════════════════ -->
    <AccountCard2 {account} />

    <!-- Toggle detail view -->
    <Button
        class="w-full"
        color="light"
        onclick={() => (showDetail = !showDetail)}
        variant="outline">
        <i class="iconify size-4 {showDetail ? 'ph--eye-slash-bold' : 'ph--eye-bold'}"></i>
        {showDetail ? 'Hide Details' : 'Show Details'}
    </Button>

    {#if showDetail}
        <!-- ════════════════════════════════════════════ -->
        <!--  ACCOUNT INFO                              -->
        <!-- ════════════════════════════════════════════ -->
        <ResponsiveCard class="space-y-0" contentClass="p-0">
            <div class="px-5 pt-[18px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest text-base-content/50 uppercase">
                    Account Info
                </p>
            </div>

            <div>
                {#each infoRows as row, i (row.label)}
                    <hr class="mx-5 border-base-content/10 md:mx-6" />

                    <div
                        class="flex items-center justify-between gap-3 px-5 py-3 md:px-6"
                        class:pb-4={i === infoRows.length - 1}>
                        <span class="flex items-center gap-2 text-[0.8rem] text-base-content/60">
                            <i class="iconify size-3.5 text-base-content/50 {row.icon}"></i>
                            {row.label}
                        </span>
                        <span
                            class="text-[0.85rem] font-semibold text-base-content"
                            class:font-mono={row.mono}>
                            {row.value}
                        </span>
                    </div>
                {/each}
            </div>
        </ResponsiveCard>

        <!-- ════════════════════════════════════════════ -->
        <!--  MEMBERS (joint accounts only)             -->
        <!-- ════════════════════════════════════════════ -->
        {#if members.length > 0}
            <ResponsiveCard class="space-y-0" contentClass="p-0">
                <div class="flex items-center justify-between px-5 pt-[18px] pb-[14px] md:px-6">
                    <p
                        class="text-[0.63rem] font-bold tracking-widest text-base-content/50 uppercase">
                        Members
                    </p>
                    <span class="text-teal cursor-pointer text-[0.75rem] font-semibold">
                        + Invite
                    </span>
                </div>

                {#each members as member, i (member.name + member.email)}
                    {#if i > 0}
                        <hr class="mx-5 border-base-content/10 md:mx-6" />
                    {/if}

                    <div
                        class="flex items-center gap-3 px-5 py-3 md:px-6"
                        class:pb-4={i === members.length - 1}>
                        <div
                            style:background={i === 0 ? bgColor : '#7A5CB8'}
                            class="avatar flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white">
                            {getMemberInitials(member.name)}
                        </div>
                        <div class="flex-1">
                            <p class="mb-0.5 text-sm font-semibold text-base-content">
                                {member.name}
                            </p>
                            <p class="text-[0.75rem] text-base-content/60">{member.email}</p>
                        </div>
                    </div>
                {/each}
            </ResponsiveCard>
        {/if}
    {:else}
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

                    <div
                        class="flex flex-col items-center gap-5 md:flex-row md:items-start md:gap-6">
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
                                    <span class="flex-1 text-sm text-base-content"
                                        >{item.name}</span>
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
                <StatCard
                    color="error"
                    icon="trending-down"
                    label="Expenses"
                    value={expenseTotal} />
                <StatCard
                    color="primary"
                    icon="piggy-bank"
                    label="Savings"
                    value={netSavings >= 0 ? netSavings : 0} />
            </div>
        </PageSection>
    {/if}
</div>
