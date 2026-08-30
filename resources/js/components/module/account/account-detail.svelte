<script lang="ts" module>
    /** Mask an account number for display: "1234567890" => "•••• •••• •••• 1234" */
    function maskAccountNumber(num: string): string {
        if (num.length <= 4) return num;
        const last4 = num.slice(-4);
        const masked = num.slice(0, -4).replace(/\d/g, '•');
        // Insert a space every 4 characters
        const groups: string[] = [];
        for (let i = 0; i < masked.length; i += 4) {
            groups.push(masked.slice(i, i + 4));
        }
        if (groups.length > 0 && groups.join('').length < 16) {
            return [...groups, last4].join(' ');
        }

        return `•••• •••• •••• ${last4}`;
    }
</script>

<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import AccountType from '@wayfinder/App/Enums/AccountType';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    interface MonthlyStat {
        inflow: number;
        inflow_count: number;
        outflow: number;
        outflow_count: number;
        period: string;
    }

    interface Props {
        account: App.Models.Account;
        monthlyStats?: MonthlyStat | null;
        members?: Array<{ name: string; email: string; role: string }>;
    }

    let {
        account,
        monthlyStats = {
            inflow: 12500000,
            inflow_count: 8,
            outflow: 8750000,
            outflow_count: 14,
            period: 'August 2026',
        },
        members = [
            { name: 'John Doe', email: 'john@example.com', role: 'Owner' },
            { name: 'Jane Smith', email: 'jane@example.com', role: 'Member' },
        ],
    }: Props = $props();

    // ── Prototype: credit card dummy data ──────────────────────
    const creditLimit = 10000000;
    const creditUsed = 3500000;
    const dueDate = '2026-09-15';
    const minPayment = 350000;

    // ── Derived state ─────────────────────────────────────────────
    const colorSlug = $derived(account.decorations?.color);
    const iconSlug = $derived(account.decorations?.icon);

    const colorObj = $derived(colorSlug ? getDecorationColor(colorSlug) : undefined);
    const iconObj = $derived(iconSlug ? getDecorationIcon(iconSlug) : undefined);

    const bgColor = $derived(colorObj?.oklch ?? 'oklch(0.45 0.08 160)');

    const accentText = $derived(colorObj?.text_color ?? '#FFFFFF');

    const isCreditCard = $derived(account.type === AccountType.CreditCard);

    const balanceLabel = $derived(isCreditCard ? 'Available' : 'Balance');

    const providerName = $derived<string | undefined>(
        (account.provider as { name?: string } | null)?.name
    );

    // ── Balance / number visibility toggles ──────────────────────
    let balanceHidden = $state(false);
    let numberRevealed = $state(false);

    function toggleBalance(): void {
        balanceHidden = !balanceHidden;
    }

    function toggleNumber(): void {
        numberRevealed = !numberRevealed;
    }

    // ── Sparkline props (simple SVG line for visual interest) ────
    const sparkPoints = $derived.by(() => {
        // Generate a subtle up-trending curve
        const pts: string[] = [];
        for (let i = 0; i <= 12; i++) {
            const x = (i / 12) * 360;
            const y = 52 - 28 * (1 - Math.exp(-i / 6)) - Math.random() * 6;
            pts.push(`${x.toFixed(1)},${y.toFixed(1)}`);
        }

        return pts.join(' ');
    });

    // ── Quick actions by account type ────────────────────────────
    interface ActionItem {
        icon: string;
        label: string;
        bgClass: string;
        textClass: string;
    }

    const actionRegistry: Record<string, ActionItem> = {
        transact: {
            icon: 'ph--arrows-left-right-bold',
            label: 'Transact',
            bgClass: 'bg-teal/10',
            textClass: 'text-teal',
        },
        transfer: {
            icon: 'ph--arrow-up-right-bold',
            label: 'Transfer',
            bgClass: 'bg-sage/10',
            textClass: 'text-sage',
        },
        report: {
            icon: 'ph--chart-bar-bold',
            label: 'Report',
            bgClass: 'bg-amber/10',
            textClass: 'text-amber',
        },
        connect: {
            icon: 'ph--link-bold',
            label: 'Connect',
            bgClass: 'bg-purple/10',
            textClass: 'text-purple',
        },
    };

    const accountActions: Record<string, string[]> = {
        [AccountType.DebitAccount]: ['transact', 'transfer', 'report', 'connect'],
        [AccountType.CreditCard]: ['transact', 'transfer', 'report', 'connect'],
        [AccountType.CashWallet]: ['transact', 'transfer', 'report'],
        [AccountType.EWallet]: ['transact', 'transfer', 'report'],
        [AccountType.Investment]: ['transact', 'transfer', 'report'],
    };

    const quickActions = $derived(
        (accountActions[account.type] ?? []).map((key) => actionRegistry[key])
    );

    // ── Info rows ────────────────────────────────────────────────
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

    function getMemberInitials(name: string): string {
        return name
            .split(' ')
            .map((w) => w[0])
            .join('')
            .toUpperCase()
            .slice(0, 2);
    }
</script>

<div class="space-y-5">
    <!-- ════════════════════════════════════════════ -->
    <!--  HERO ACCOUNT CARD                         -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="overflow-x-clip" contentClass="p-0">
        <div
            style:background={bgColor}
            class="relative overflow-hidden px-5 pt-5 pb-4 md:px-6 md:pb-5">
            <!-- Decorative circles -->
            <div
                style="background: rgba(255,255,255,0.06)"
                class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full">
            </div>
            <div
                style="background: rgba(255,255,255,0.04)"
                class="pointer-events-none absolute right-10 -bottom-8 size-28 rounded-full">
            </div>

            <!-- Header row: provider icon + name + hide toggle -->
            <div class="relative z-1 mb-5 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div
                        style="background: rgba(255,255,255,0.12)"
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl">
                        {#if iconObj?.value}
                            <i class="iconify size-4.5 text-white {iconObj.value}"></i>
                        {:else if account.type === AccountType.CreditCard}
                            <i class="iconify size-4.5 text-white ph--credit-card-bold"></i>
                        {:else if account.type === AccountType.EWallet}
                            <i class="iconify size-4.5 text-white ph--device-mobile-bold"></i>
                        {:else if account.type === AccountType.CashWallet}
                            <i class="iconify size-4.5 text-white ph--wallet-bold"></i>
                        {:else}
                            <i class="iconify size-4.5 text-white ph--bank-bold"></i>
                        {/if}
                    </div>
                    <div>
                        {#if providerName}
                            <p
                                style="color: rgba(255,255,255,0.45)"
                                class="text-[0.68rem] font-semibold tracking-wider uppercase">
                                {providerName}
                            </p>
                        {/if}
                        <p class="text-sm leading-tight font-semibold text-white">
                            {account.name}
                        </p>
                    </div>
                </div>

                <button
                    style="background: rgba(255,255,255,0.1)"
                    class="flex cursor-pointer items-center justify-center rounded-lg border-none p-1.5"
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
            <div class="relative z-1">
                <p
                    style="color: rgba(255,255,255,0.45)"
                    class="mb-1 text-[0.63rem] font-semibold tracking-widest uppercase">
                    {balanceLabel}
                </p>
                <div class="flex items-start gap-1">
                    {#if !balanceHidden}
                        <span
                            style="color: rgba(255,255,255,0.4); margin-top: 4px;"
                            class="font-mono text-sm">
                            Rp
                        </span>
                    {/if}
                    <span
                        class="font-mono text-[clamp(1.8rem,8vw,2.4rem)] leading-none font-medium tracking-tight text-white">
                        {#if balanceHidden}
                            ••••••
                        {:else}
                            {Formatter.currency(
                                account.current_balance ?? account.initial_balance ?? 0,
                                true
                            )}
                        {/if}
                    </span>
                </div>
                <p style="color: rgba(255,255,255,0.35)" class="mt-1 text-[0.68rem]">
                    {account.currency ?? 'IDR'}
                </p>
            </div>

            <!-- Sparkline + account number row -->
            <div class="relative z-1 mt-4">
                {#if !balanceHidden}
                    <svg
                        class="mb-1 block h-11 w-full"
                        preserveAspectRatio="none"
                        viewBox="0 0 360 52">
                        <defs>
                            <linearGradient id="spark-grad" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="rgba(255,255,255,0.18)" />
                                <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                            </linearGradient>
                        </defs>
                        <polygon
                            fill="url(#spark-grad)"
                            points="{sparkPoints} 360,52 0,52"
                            stroke="none" />
                        <polyline
                            fill="none"
                            points={sparkPoints}
                            stroke="rgba(255,255,255,0.45)"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8" />
                    </svg>
                {/if}

                <div class="flex items-center justify-between pt-0.5">
                    {#if account.account_number}
                        <p
                            style="color: rgba(255,255,255,0.4)"
                            class="font-mono text-[0.78rem] tracking-widest">
                            {numberRevealed
                                ? account.account_number
                                : maskAccountNumber(account.account_number)}
                        </p>
                        <button
                            style="color: rgba(255,255,255,0.45)"
                            class="cursor-pointer border-none bg-transparent p-0 text-[0.72rem] font-semibold"
                            onclick={toggleNumber}>
                            {numberRevealed ? 'Hide' : 'Show'}
                        </button>
                    {/if}
                </div>
            </div>
        </div>
    </ResponsiveCard>

    <!-- ════════════════════════════════════════════ -->
    <!--  QUICK ACTIONS                             -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="space-y-0" contentClass="p-0">
        <div class="flex gap-2.5 p-4 pb-5">
            {#each quickActions as action (action.label)}
                <button
                    class="hover:bg-sage/5 flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-[16px] border-none p-4 pt-3.5 transition-colors"
                    type="button">
                    <div
                        class="flex size-11 items-center justify-center rounded-[14px] {action.bgClass}">
                        <i class="iconify size-5 {action.textClass} {action.icon}"></i>
                    </div>
                    <span class="text-[0.75rem] font-semibold text-base-content/70">
                        {action.label}
                    </span>
                </button>
            {/each}
        </div>
    </ResponsiveCard>

    <!-- ════════════════════════════════════════════ -->
    <!--  MONTHLY STATS (non-credit)                -->
    <!-- ════════════════════════════════════════════ -->
    {#if !isCreditCard && monthlyStats}
        <ResponsiveCard class="space-y-0" contentClass="p-0">
            <!-- Month header -->
            <div class="flex items-center justify-between px-5 pt-[18px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest text-base-content/50 uppercase">
                    {monthlyStats.period}
                </p>
                <span class="text-teal cursor-pointer text-[0.75rem] font-semibold">
                    See Report &rarr;
                </span>
            </div>

            <!-- In / Out -->
            <div class="flex">
                <div class="flex-1 border-r border-base-content/10 px-5 py-[18px] md:px-6">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span
                            class="bg-teal/10 text-teal flex size-5 items-center justify-center rounded-md text-[0.65rem] font-bold">
                            &uarr;
                        </span>
                        <span class="text-[0.72rem] font-semibold text-base-content/50"
                            >Inflow</span>
                    </div>
                    <p class="text-teal font-mono text-base font-medium">
                        {Formatter.currency(monthlyStats.inflow)}
                    </p>
                    <p class="mt-0.5 text-[0.72rem] text-base-content/50">
                        {monthlyStats.inflow_count} transactions
                    </p>
                </div>
                <div class="flex-1 px-5 py-[18px] md:px-6">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span
                            class="flex size-5 items-center justify-center rounded-md bg-error/10 text-[0.65rem] font-bold text-error">
                            &darr;
                        </span>
                        <span class="text-[0.72rem] font-semibold text-base-content/50"
                            >Outflow</span>
                    </div>
                    <p class="font-mono text-base font-medium text-error">
                        {Formatter.currency(monthlyStats.outflow)}
                    </p>
                    <p class="mt-0.5 text-[0.72rem] text-base-content/50">
                        {monthlyStats.outflow_count} transactions
                    </p>
                </div>
            </div>

            <!-- Ratio bar -->
            {@const total = monthlyStats.inflow + monthlyStats.outflow}
            {@const inflowPct = total > 0 ? (monthlyStats.inflow / total) * 100 : 50}
            {@const netAmount = monthlyStats.inflow - monthlyStats.outflow}
            <div class="px-5 pb-[14px] md:px-6">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="text-[0.69rem] text-base-content/50">Inflow/Outflow Ratio</span>
                    <span
                        class="font-mono text-[0.75rem] font-medium {netAmount >= 0
                            ? 'text-teal'
                            : 'text-error'}">
                        {netAmount >= 0 ? '+' : ''}{Formatter.currency(netAmount, true)}
                    </span>
                </div>
                <div class="h-1.5 overflow-hidden rounded-full bg-error/20">
                    <div
                        style="width: {inflowPct}%"
                        class="bg-teal h-full rounded-full transition-all">
                    </div>
                </div>
                <div class="mt-1 flex justify-between">
                    <span class="text-teal text-[0.65rem] font-semibold"
                        >{inflowPct.toFixed(0)}% inflow</span>
                    <span class="text-[0.65rem] font-semibold text-error"
                        >{(100 - inflowPct).toFixed(0)}% outflow</span>
                </div>
            </div>
        </ResponsiveCard>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  CREDIT CARD SECTION                       -->
    <!-- ════════════════════════════════════════════ -->
    <!-- {#if isCreditCard} -->
    <ResponsiveCard class="space-y-0" contentClass="p-0">
        <div class="px-5 pt-[18px] md:px-6">
            <p class="text-[0.63rem] font-bold tracking-widest text-base-content/50 uppercase">
                Credit Usage
            </p>
        </div>

        <div class="px-5 pt-[14px] md:px-6">
            <div class="mb-1 flex items-end justify-between">
                <span class="text-[0.78rem] text-base-content/60">Used</span>
                <div class="text-right">
                    <span class="font-mono text-sm font-semibold text-error">
                        {Formatter.currency(creditUsed, true)}
                    </span>
                    <span class="text-[0.75rem] text-base-content/50">
                        / {Formatter.currency(creditLimit, true)}
                    </span>
                </div>
            </div>

            <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-base-content/10">
                <div
                    style="width: {(creditUsed / creditLimit) * 100}%"
                    class="h-full rounded-full bg-error transition-all">
                </div>
            </div>

            <div class="mt-1.5 mb-3.5 flex justify-between">
                <span class="text-[0.69rem] font-semibold text-error"
                    >{((creditUsed / creditLimit) * 100).toFixed(1)}% used</span>
                <span class="text-teal text-[0.69rem] font-semibold"
                    >Available {Formatter.currency(creditLimit - creditUsed, true)}</span>
            </div>
        </div>

        <hr class="mx-5 border-base-content/10 md:mx-6" />

        <!-- Due date -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 md:px-6">
            <span class="flex items-center gap-2 text-[0.8rem] text-base-content/60">
                <i class="iconify size-3.5 text-base-content/50 ph--calendar-bold"></i>
                Due Date
            </span>
            <span class="text-amber text-[0.85rem] font-semibold">
                {DateTimeHelper.format(dueDate, 'date')}
            </span>
        </div>

        <hr class="mx-5 border-base-content/10 md:mx-6" />

        <!-- Min payment -->
        <div class="flex items-center justify-between gap-3 px-5 py-3 pb-4 md:px-6">
            <span class="flex items-center gap-2 text-[0.8rem] text-base-content/60">
                <i class="iconify size-3.5 text-base-content/50 ph--currency-circle-dollar-bold"
                ></i>
                Min. Payment
            </span>
            <span class="font-mono text-[0.85rem] font-semibold">
                {Formatter.currency(minPayment)}
            </span>
        </div>
    </ResponsiveCard>
    <!-- {/if} -->

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
                <p class="text-[0.63rem] font-bold tracking-widest text-base-content/50 uppercase">
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
                    <!-- <span
                        class="role-badge rounded-full px-2 py-0.5 text-[0.65rem] font-bold tracking-wide uppercase"
                        class:bg-teal/10={i === 0}
                        class:text-teal={i === 0}
                        class:bg-sage/10={i !== 0}
                        class:text-sage={i !== 0}>
                        {member.role}
                    </span> -->
                </div>
            {/each}
        </ResponsiveCard>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  DETAIL FIELDS (timestamps)                -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard class="space-y-5" contentClass="p-2.5">
        <p class=" text-xs font-semibold tracking-widest text-base-content/50 uppercase">Details</p>

        <!-- Account number -->
        <div class="flex items-start gap-3">
            <i
                class="mt-0.5 iconify size-5 shrink-0 text-base-content/50 ph--identification-badge-bold"
            ></i>
            <div>
                <p class="mb-0.5 text-xs text-base-content/50">Account ID</p>
                <p class="font-mono text-sm text-base-content">#{account.id}</p>
            </div>
        </div>

        <hr class="border-base-content/10" />

        <!-- Created / Updated -->
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <i class="mt-0.5 iconify size-5 shrink-0 text-base-content/50 ph--clock-bold"></i>
                <div>
                    <p class="mb-0.5 text-xs text-base-content/50">Created</p>
                    <p class="text-sm text-base-content">
                        {DateTimeHelper.format(account.created_at, 'datetime')}
                    </p>
                </div>
            </div>
            {#if account.updated_at}
                <div class="text-right">
                    <p class="mb-0.5 text-xs text-base-content/50">Updated</p>
                    <p class="text-sm text-base-content">
                        {DateTimeHelper.format(account.updated_at, 'datetime')}
                    </p>
                </div>
            {/if}
        </div>
    </ResponsiveCard>
</div>
