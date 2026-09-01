<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import { Collapsible } from 'bits-ui';
    import { SvelteMap } from 'svelte/reactivity';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import PageSection from '@components/layouts/page-section.svelte';
    import AccountCard from '@components/module/account/account-card.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import StatCard from '@components/ui/cards/stat-card.svelte';
    import DonutChart from '@components/ui/charts/donut-chart.svelte';

    let { account }: { account: App.Models.Account } = $props();

    setLayoutProps({ backUrl: AccountController.index.url() });

    const providerName = $derived<string | undefined>(
        (account.provider as { name?: string } | null)?.name
    );

    let showDetail = $state(false);
    let transactionsOpen = $state(true);
    let categoryOpen = $state(true);

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

<DashboardPageHeader title="Account Detail">
    <div class="flex items-center gap-3">
        <Button color="light" href={AccountController.index.url()} variant="outline">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="space-y-1.5">
            <h1 class="text-xl font-bold">{account.name}</h1>
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
    <AccountCard {account} />

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
        <Card contentClass="space-y-3">
            <h5 class="text-sm font-bold tracking-wider text-base-content/80 uppercase">
                Account Info
            </h5>

            <ul>
                <hr class="border-base-content/20" />
                {#each infoRows as row, i (row.label)}
                    <li class="flex items-center justify-between gap-3 py-3">
                        <span class="flex items-center gap-2 text-sm text-base-content/80">
                            <i class="iconify size-4 text-base-content/80 {row.icon}"></i>
                            {row.label}
                        </span>
                        <span class="text-sm font-medium text-base-content">
                            {row.value}
                        </span>
                    </li>

                    <hr class="border-base-content/20" />
                {/each}
            </ul>
        </Card>

        <!-- ════════════════════════════════════════════ -->
        <!--  MEMBERS (joint accounts only)             -->
        <!-- ════════════════════════════════════════════ -->
        {#if members.length > 0}
            <Card contentClass="space-y-3">
                <h5 class="text-sm font-bold tracking-wider text-base-content/80 uppercase">
                    Members
                </h5>

                <ul>
                    {#each members as member, i (member.name + member.email)}
                        {#if i > 0}
                            <hr class="border-base-content/20" />
                        {/if}

                        <li class="flex items-center gap-3 py-3">
                            <div
                                style:background={bgColor}
                                class="avatar flex size-10 shrink-0 items-center justify-center rounded-md text-sm font-bold text-white">
                                {getMemberInitials(member.name)}
                            </div>

                            <div class="flex-1">
                                <p class="mb-0.5 text-sm font-semibold text-base-content">
                                    {member.name}
                                </p>
                                <p class="text-sm text-base-content/80">{member.email}</p>
                            </div>
                        </li>
                    {/each}
                </ul>
            </Card>
        {/if}
    {:else}
        <!-- ════════════════════════════════════════════ -->
        <!--  SPENDING BY CATEGORY                        -->
        <!-- ════════════════════════════════════════════ -->
        {#if categorySpending.length > 0}
            <PageSection>
                <Collapsible.Root bind:open={categoryOpen}>
                    <Card class=" {!transactionsOpen ? 'gap-0' : ''}">
                        {#snippet header()}
                            <Collapsible.Trigger
                                class="flex w-full cursor-pointer items-center justify-between">
                                <p class="text-sm font-bold tracking-wide uppercase">
                                    Spending Category {currentMonthLabel}
                                </p>
                                <div class="flex items-center gap-2">
                                    <i
                                        class="iconify size-4 {categoryOpen
                                            ? 'ph--caret-up-bold'
                                            : 'ph--caret-down-bold'}"></i>
                                </div>
                            </Collapsible.Trigger>
                        {/snippet}

                        <Collapsible.Content>
                            <div
                                class="flex flex-col items-center gap-5 px-5 pb-5 md:flex-row md:items-start md:gap-6 md:px-6">
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
                                            <span
                                                class="w-8 text-right text-sm text-base-content/50">
                                                {item.percentage}%
                                            </span>
                                        </div>
                                    {/each}
                                </div>
                            </div>
                        </Collapsible.Content>
                    </Card>
                </Collapsible.Root>
            </PageSection>
        {/if}

        <!-- ════════════════════════════════════════════ -->
        <!--  RECENT TRANSACTIONS                         -->
        <!-- ════════════════════════════════════════════ -->
        <PageSection>
            <Collapsible.Root bind:open={transactionsOpen}>
                <Card class=" {!transactionsOpen ? 'gap-0' : ''}">
                    {#snippet header()}
                        <Collapsible.Trigger
                            class="flex w-full cursor-pointer items-center justify-between">
                            <p class="text-sm font-bold tracking-wide uppercase">
                                Recent Transactions
                            </p>
                            <i
                                class="iconify size-4 {transactionsOpen
                                    ? 'ph--caret-up-bold'
                                    : 'ph--caret-down-bold'}"></i>
                        </Collapsible.Trigger>
                    {/snippet}
                    <Collapsible.Content>
                        <div>
                            {#if transactions.length > 0}
                                <TransactionList {transactions} />
                            {:else}
                                <EmptyItemPlaceholder label="No Transaction Yet" />
                            {/if}
                        </div>
                    </Collapsible.Content>
                </Card>
            </Collapsible.Root>
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
