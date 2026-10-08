<script lang="ts">
    import type { CategorySpendingReportData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { Collapsible } from 'bits-ui';

    import DateTimeHelper from '@utilities/date-time-helper';
    import StringHelper from '@utilities/string-helper';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import AccountCard from '@components/module/account/account-card.svelte';
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    let {
        account,
        transactions,
        categorySpending,
    }: {
        account: App.Models.Account;
        transactions: TransactionList[];
        categorySpending: CategorySpendingReportData;
    } = $props();

    setLayoutProps({ title: account?.name, backUrl: AccountController.index.url() });

    let showDetail = $state(false);
    let transactionsOpen = $state(true);
    let categoryOpen = $state(true);

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

    const details = $derived.by<InfoRow[]>(() => {
        const rows: InfoRow[] = [
            {
                icon: 'solar--user-id-bold-duotone',
                label: 'Account Type',
                value: account.type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()),
                mono: false,
            },
        ];

        if (account.provider) {
            rows.push({
                icon: 'solar--buildings-bold-duotone',
                label: 'Provider',
                value: account.provider?.name,
                mono: false,
            });
        }

        if (account.access_type) {
            rows.push({
                icon: 'solar--users-group-two-rounded-bold-duotone',
                label: 'Access Type',
                value: account.access_type === 'personal' ? 'Personal' : 'Joint',
                mono: false,
            });
        }

        rows.push({
            icon: 'solar--calendar-bold-duotone',
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
</script>

<HeaderContext>
    {#snippet actions()}
        <Button
            color="primary"
            href={AccountController.edit.url({ account: account.id })}
            size="icon"
            variant="soft">
            <i class="ml-0.5 iconify size-4 solar--pen-new-square-line-duotone"></i>
        </Button>
    {/snippet}
</HeaderContext>

<MobilePageLayout variant="3/5">
    {#snippet hero()}
        <AccountCard {account} hideEdit />
    {/snippet}

    <div class="space-y-5">
        <!-- Toggle detail view -->
        <Button
            class="w-full hover:bg-white"
            color="light"
            onclick={() => (showDetail = !showDetail)}
            variant="outline">
            <i class="iconify size-5 solar--hamburger-menu-linear"></i>
            {showDetail ? 'Hide Account Details' : 'Show Account Details'}
        </Button>

        {#if showDetail}
            <ResponsiveCard contentClass="space-y-3">
                <h5 class="text-sm font-bold tracking-wider text-foreground uppercase">
                    Account Info
                </h5>

                <ul>
                    <hr class="border-border" />
                    {#each details as row, i (row.label)}
                        <li class="flex items-center justify-between gap-3 py-3">
                            <span class="flex items-center gap-2 text-sm text-foreground">
                                <i class="iconify size-5 text-foreground {row.icon}"></i>
                                {row.label}
                            </span>
                            <span class="text-sm font-medium text-foreground">
                                {row.value}
                            </span>
                        </li>

                        <hr class="border-border" />
                    {/each}
                </ul>
            </ResponsiveCard>

            {#if members.length > 0}
                <ResponsiveCard contentClass="space-y-3">
                    <h5 class="text-sm font-bold tracking-wider text-foreground uppercase">
                        Members
                    </h5>

                    <ul>
                        {#each members as member, i (member.name + member.email)}
                            {#if i > 0}
                                <hr class="border-border" />
                            {/if}

                            <li class="flex items-center gap-3 py-3">
                                <div
                                    style:background={bgColor}
                                    class="avatar flex size-10 shrink-0 items-center justify-center rounded-md text-sm font-bold text-white">
                                    {StringHelper.getInitials(member.name)}
                                </div>

                                <div class="flex-1">
                                    <p class="mb-0.5 text-sm font-semibold text-foreground">
                                        {member.name}
                                    </p>
                                    <p class="text-sm text-foreground">{member.email}</p>
                                </div>
                            </li>
                        {/each}
                    </ul>
                </ResponsiveCard>
            {/if}
        {:else}
            <!-- ════════════════════════════════════════════ -->
            <!--  SPENDING BY CATEGORY                        -->
            <!-- ════════════════════════════════════════════ -->
            {#if categorySpending}
                <Collapsible.Root bind:open={categoryOpen}>
                    <ResponsiveCard class=" {!transactionsOpen ? 'gap-0' : ''}">
                        {#snippet header()}
                            <Collapsible.Trigger
                                class="flex w-full cursor-pointer items-center justify-between">
                                <p class="text-sm font-bold tracking-wide uppercase">
                                    Spending Category {currentMonthLabel}
                                </p>
                                <div class="flex items-center gap-2">
                                    <i
                                        class="iconify size-4 {categoryOpen
                                            ? 'solar--alt-arrow-up-line-duotone'
                                            : 'solar--alt-arrow-down-line-duotone'}"></i>
                                </div>
                            </Collapsible.Trigger>
                        {/snippet}

                        <Collapsible.Content>
                            <CategorySpendingChart {categorySpending} />
                        </Collapsible.Content>
                    </ResponsiveCard>
                </Collapsible.Root>
            {/if}

            <!-- ════════════════════════════════════════════ -->
            <!--  RECENT TRANSACTIONS                         -->
            <!-- ════════════════════════════════════════════ -->

            <Collapsible.Root bind:open={transactionsOpen}>
                <ResponsiveCard class=" {!transactionsOpen ? 'gap-0' : ''}">
                    {#snippet header()}
                        <Collapsible.Trigger
                            class="flex w-full cursor-pointer items-center justify-between">
                            <p class="text-sm font-bold tracking-wide uppercase">
                                Recent Transactions
                            </p>
                            <i
                                class="iconify size-4 {transactionsOpen
                                    ? 'solar--alt-arrow-up-line-duotone'
                                    : 'solar--alt-arrow-down-line-duotone'}"></i>
                        </Collapsible.Trigger>
                    {/snippet}
                    <Collapsible.Content>
                        <div class="space-y-1.5">
                            {#if transactions.length > 0}
                                <TransactionList
                                    loadMoreAction={() =>
                                        router.visit(TransactionController.index.url())}
                                    {transactions} />
                            {:else}
                                <EmptyItemPlaceholder label="No Transaction Yet" />
                            {/if}
                        </div>
                    </Collapsible.Content>
                </ResponsiveCard>
            </Collapsible.Root>
        {/if}
    </div>
</MobilePageLayout>
