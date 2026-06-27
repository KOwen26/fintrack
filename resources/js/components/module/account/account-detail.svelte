<script lang="ts" context="module">
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

    import AccountAccessTypeBadge from './account-access-type-badge.svelte';
    import AccountTypeBadge from './account-type-badge.svelte';

    import AccountType from '@wayfinder/App/Enums/AccountType';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    interface MonthlyStat {
        inflow: number;
        inflow_count: number;
        outflow: number;
        outflow_count: number;
        period: string;
    }

    interface TransactionRow {
        id: number;
        type: App.Enums.TransactionType;
        description: string | null;
        amount: number;
        transaction_date: string;
        category?: App.Models.Category | null;
    }

    interface Props {
        account: App.Models.Account;
        monthlyStats?: MonthlyStat | null;
        recentTransactions?: TransactionRow[];
        members?: Array<{ name: string; email: string; role: string }>;
    }

    let { account, monthlyStats = null, recentTransactions = [], members = [] }: Props = $props();

    // ── Derived state ─────────────────────────────────────────────
    const color = $derived(account.decorations?.color);
    const icon = $derived(account.decorations?.icon);

    const bgColor = $derived(color?.value ?? 'oklch(0.45 0.08 160)');

    const accentText = $derived(color?.text_color ?? '#FFFFFF');

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

    const quickActions = $derived.by<ActionItem[]>(() => {
        const base: ActionItem[] = [];

        switch (account.type) {
            case AccountType.DebitAccount:
                base.push(
                    {
                        icon: 'ph--arrow-up-right-bold',
                        label: 'Transfer',
                        bgClass: 'bg-teal/10',
                        textClass: 'text-teal',
                    },
                    {
                        icon: 'ph--plus-bold',
                        label: 'Top-up',
                        bgClass: 'bg-sage/10',
                        textClass: 'text-sage',
                    },
                    {
                        icon: 'ph--chart-bar-bold',
                        label: 'Report',
                        bgClass: 'bg-amber/10',
                        textClass: 'text-amber',
                    },
                    {
                        icon: 'ph--link-bold',
                        label: 'Connect',
                        bgClass: 'bg-purple/10',
                        textClass: 'text-purple',
                    }
                );
                break;

            case AccountType.CreditCard:
                base.push(
                    {
                        icon: 'ph--credit-card-bold',
                        label: 'Pay',
                        bgClass: 'bg-teal/10',
                        textClass: 'text-teal',
                    },
                    {
                        icon: 'ph--lock-bold',
                        label: 'Lock',
                        bgClass: 'bg-error/10',
                        textClass: 'text-error',
                    },
                    {
                        icon: 'ph--file-text-bold',
                        label: 'Bill',
                        bgClass: 'bg-amber/10',
                        textClass: 'text-amber',
                    },
                    {
                        icon: 'ph--chart-bar-bold',
                        label: 'Report',
                        bgClass: 'bg-purple/10',
                        textClass: 'text-purple',
                    }
                );
                break;

            case AccountType.CashWallet:
                base.push(
                    {
                        icon: 'ph--plus-bold',
                        label: 'Add Money',
                        bgClass: 'bg-teal/10',
                        textClass: 'text-teal',
                    },
                    {
                        icon: 'ph--minus-bold',
                        label: 'Withdraw',
                        bgClass: 'bg-error/10',
                        textClass: 'text-error',
                    },
                    {
                        icon: 'ph--arrows-clockwise-bold',
                        label: 'Adjust',
                        bgClass: 'bg-amber/10',
                        textClass: 'text-amber',
                    },
                    {
                        icon: 'ph--chart-bar-bold',
                        label: 'Report',
                        bgClass: 'bg-purple/10',
                        textClass: 'text-purple',
                    }
                );
                break;

            case AccountType.EWallet:
                base.push(
                    {
                        icon: 'ph--arrow-up-right-bold',
                        label: 'Transfer',
                        bgClass: 'bg-teal/10',
                        textClass: 'text-teal',
                    },
                    {
                        icon: 'ph--plus-bold',
                        label: 'Top-up',
                        bgClass: 'bg-sage/10',
                        textClass: 'text-sage',
                    },
                    {
                        icon: 'ph--receipt-bold',
                        label: 'Bill',
                        bgClass: 'bg-amber/10',
                        textClass: 'text-amber',
                    },
                    {
                        icon: 'ph--chart-bar-bold',
                        label: 'Report',
                        bgClass: 'bg-purple/10',
                        textClass: 'text-purple',
                    }
                );
                break;

            case AccountType.Investment:
                base.push(
                    {
                        icon: 'ph--arrow-up-right-bold',
                        label: 'Deposit',
                        bgClass: 'bg-teal/10',
                        textClass: 'text-teal',
                    },
                    {
                        icon: 'ph--arrow-down-left-bold',
                        label: 'Withdraw',
                        bgClass: 'bg-error/10',
                        textClass: 'text-error',
                    },
                    {
                        icon: 'ph--chart-line-bold',
                        label: 'Performance',
                        bgClass: 'bg-amber/10',
                        textClass: 'text-amber',
                    },
                    {
                        icon: 'ph--chart-bar-bold',
                        label: 'Report',
                        bgClass: 'bg-purple/10',
                        textClass: 'text-purple',
                    }
                );
                break;
        }

        return base;
    });

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

    // ── Helpers ──────────────────────────────────────────────────
    const isInflow = $derived(
        (t: { type: App.Enums.TransactionType }) => t.type === 'income' || t.type === 'transfer_in'
    );

    function getTxnColor(type: App.Enums.TransactionType): string {
        if (type === 'income' || type === 'transfer_in') return 'text-success';
        if (type === 'transfer_out' || type === 'fee') return 'text-warning';
        return 'text-error';
    }

    function getTxnIcon(type: App.Enums.TransactionType): string {
        if (type === 'income' || type === 'transfer_in') return 'ph--plus-bold';
        if (type === 'transfer_out') return 'ph--arrow-fat-right-bold';
        if (type === 'fee') return 'ph--minus-bold';
        return 'ph--minus-bold';
    }

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
    <ResponsiveCard wrapperClass="overflow-x-clip" class="p-0">
        <div
            style:background={bgColor}
            class="relative overflow-hidden px-5 pb-4 pt-5 md:px-6 md:pb-5">
            <!-- Decorative circles -->
            <div
                class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full"
                style="background: rgba(255,255,255,0.06)">
            </div>
            <div
                class="pointer-events-none absolute -bottom-8 right-10 size-28 rounded-full"
                style="background: rgba(255,255,255,0.04)">
            </div>

            <!-- Header row: provider icon + name + hide toggle -->
            <div class="relative z-1 flex items-center justify-between mb-5">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl"
                        style="background: rgba(255,255,255,0.12)">
                        {#if icon?.value}
                            <i class="iconify size-4.5 text-white {icon.value}"></i>
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
                                class="text-[0.68rem] font-semibold tracking-wider uppercase"
                                style="color: rgba(255,255,255,0.45)">
                                {providerName}
                            </p>
                        {/if}
                        <p class="text-sm font-semibold text-white leading-tight">
                            {account.name}
                        </p>
                    </div>
                </div>

                <button
                    onclick={toggleBalance}
                    class="flex cursor-pointer items-center justify-center rounded-lg border-none p-1.5"
                    style="background: rgba(255,255,255,0.1)"
                    aria-label="Toggle balance visibility">
                    {#if balanceHidden}
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="rgba(255,255,255,0.6)"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <path
                                d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
                            <line x1="1" y1="1" x2="23" y2="23" />
                        </svg>
                    {:else}
                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="rgba(255,255,255,0.6)"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
                            <circle cx="12" cy="12" r="3" />
                        </svg>
                    {/if}
                </button>
            </div>

            <!-- Balance -->
            <div class="relative z-1">
                <p
                    class="text-[0.63rem] font-semibold tracking-widest uppercase mb-1"
                    style="color: rgba(255,255,255,0.45)">
                    {balanceLabel}
                </p>
                <div class="flex items-start gap-1">
                    {#if !balanceHidden}
                        <span
                            class="font-mono text-sm"
                            style="color: rgba(255,255,255,0.4); margin-top: 4px;">
                            Rp
                        </span>
                    {/if}
                    <span
                        class="font-mono text-[clamp(1.8rem,8vw,2.4rem)] font-medium leading-none tracking-tight text-white">
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
                <p class="text-[0.68rem] mt-1" style="color: rgba(255,255,255,0.35)">
                    {account.currency ?? 'IDR'}
                </p>
            </div>

            <!-- Sparkline + account number row -->
            <div class="relative z-1 mt-4">
                {#if !balanceHidden}
                    <svg
                        viewBox="0 0 360 52"
                        preserveAspectRatio="none"
                        class="mb-1 block h-11 w-full">
                        <defs>
                            <linearGradient id="spark-grad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="rgba(255,255,255,0.18)" />
                                <stop offset="100%" stop-color="rgba(255,255,255,0)" />
                            </linearGradient>
                        </defs>
                        <polygon
                            points="{sparkPoints} 360,52 0,52"
                            fill="url(#spark-grad)"
                            stroke="none" />
                        <polyline
                            points={sparkPoints}
                            fill="none"
                            stroke="rgba(255,255,255,0.45)"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                {/if}

                <div class="flex items-center justify-between pt-0.5">
                    {#if account.account_number}
                        <p
                            class="font-mono text-[0.78rem] tracking-widest"
                            style="color: rgba(255,255,255,0.4)">
                            {numberRevealed
                                ? account.account_number
                                : maskAccountNumber(account.account_number)}
                        </p>
                        <button
                            onclick={toggleNumber}
                            class="cursor-pointer border-none bg-transparent p-0 text-[0.72rem] font-semibold"
                            style="color: rgba(255,255,255,0.45)">
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
    <ResponsiveCard wrapperClass="space-y-0" class="p-0">
        <div class="flex gap-2.5 p-4 pb-5">
            {#each quickActions as action (action.label)}
                <button
                    class="flex flex-1 cursor-pointer flex-col items-center gap-2 rounded-[16px] border-none p-4 pt-3.5 transition-colors hover:bg-sage/5"
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
        <ResponsiveCard wrapperClass="space-y-0" class="p-0">
            <!-- Month header -->
            <div class="flex items-center justify-between px-5 pt-[18px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest uppercase text-base-content/50">
                    {monthlyStats.period}
                </p>
                <span class="text-[0.75rem] font-semibold text-teal cursor-pointer">
                    See Report &rarr;
                </span>
            </div>

            <!-- In / Out -->
            <div class="flex">
                <div class="flex-1 border-r border-base-content/10 px-5 py-[18px] md:px-6">
                    <div class="mb-1.5 flex items-center gap-1.5">
                        <span
                            class="flex size-5 items-center justify-center rounded-md bg-teal/10 text-[0.65rem] font-bold text-teal">
                            &uarr;
                        </span>
                        <span class="text-[0.72rem] font-semibold text-base-content/50"
                            >Inflow</span>
                    </div>
                    <p class="font-mono text-base font-medium text-teal">
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
                        class="h-full rounded-full bg-teal transition-all"
                        style="width: {inflowPct}%">
                    </div>
                </div>
                <div class="mt-1 flex justify-between">
                    <span class="text-[0.65rem] font-semibold text-teal"
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
    {#if isCreditCard}
        <ResponsiveCard wrapperClass="space-y-0" class="p-0">
            <div class="px-5 pt-[18px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest uppercase text-base-content/50">
                    Credit Usage
                </p>
            </div>

            <div class="px-5 pt-[14px] md:px-6">
                {#if account.credit_card_limit && account.credit_card_limit > 0}
                    {@const used =
                        (account.current_balance ?? 0) > 0
                            ? Math.min(account.current_balance ?? 0, account.credit_card_limit)
                            : 0}
                    {@const usagePct = (used / account.credit_card_limit) * 100}
                    {@const available = account.credit_card_limit - used}

                    <div class="mb-1 flex items-end justify-between">
                        <span class="text-[0.78rem] text-base-content/60">Used</span>
                        <div class="text-right">
                            <span class="font-mono text-sm font-semibold text-error">
                                {Formatter.currency(used, true)}
                            </span>
                            <span class="text-[0.75rem] text-base-content/50">
                                / {Formatter.currency(account.credit_card_limit, true)}
                            </span>
                        </div>
                    </div>

                    <div class="mt-2.5 h-2 overflow-hidden rounded-full bg-base-content/10">
                        <div
                            class="h-full rounded-full bg-error transition-all"
                            style="width: {usagePct}%">
                        </div>
                    </div>

                    <div class="mb-3.5 mt-1.5 flex justify-between">
                        <span class="text-[0.69rem] font-semibold text-error"
                            >{usagePct.toFixed(1)}% used</span>
                        <span class="text-[0.69rem] font-semibold text-teal"
                            >Available {Formatter.currency(available, true)}</span>
                    </div>
                {/if}
            </div>

            <hr class="border-base-content/10 mx-5 md:mx-6" />

            <!-- Due date -->
            <div class="flex items-center justify-between gap-3 px-5 py-3 md:px-6">
                <span class="flex items-center gap-2 text-[0.8rem] text-base-content/60">
                    <i class="iconify size-3.5 text-base-content/50 ph--calendar-bold"></i>
                    Due Date
                </span>
                <span class="text-[0.85rem] font-semibold text-amber">
                    {#if account.due_date}
                        {DateTimeHelper.format(account.due_date, 'date')}
                    {:else}
                        --
                    {/if}
                </span>
            </div>

            <hr class="border-base-content/10 mx-5 md:mx-6" />

            <!-- Min payment -->
            <div class="flex items-center justify-between gap-3 px-5 py-3 pb-4 md:px-6">
                <span class="flex items-center gap-2 text-[0.8rem] text-base-content/60">
                    <i class="iconify size-3.5 text-base-content/50 ph--currency-circle-dollar-bold"
                    ></i>
                    Min. Payment
                </span>
                <span class="font-mono text-[0.85rem] font-semibold">
                    {Formatter.currency(account.min_payment ?? 0)}
                </span>
            </div>
        </ResponsiveCard>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  ACCOUNT INFO                              -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard wrapperClass="space-y-0" class="p-0">
        <div class="px-5 pt-[18px] md:px-6">
            <p class="text-[0.63rem] font-bold tracking-widest uppercase text-base-content/50">
                Account Info
            </p>
        </div>

        <div>
            {#each infoRows as row, i (row.label)}
                <hr class="border-base-content/10 mx-5 md:mx-6" />

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
        <ResponsiveCard wrapperClass="space-y-0" class="p-0">
            <div class="flex items-center justify-between px-5 pt-[18px] pb-[14px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest uppercase text-base-content/50">
                    Members
                </p>
                <span class="text-[0.75rem] font-semibold text-teal cursor-pointer">
                    + Invite
                </span>
            </div>

            {#each members as member, i (member.name + member.email)}
                {#if i > 0}
                    <hr class="border-base-content/10 mx-5 md:mx-6" />
                {/if}

                <div
                    class="flex items-center gap-3 px-5 py-3 md:px-6"
                    class:pb-4={i === members.length - 1}>
                    <div
                        class="avatar flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-bold text-white"
                        style:background={i === 0 ? bgColor : '#7A5CB8'}>
                        {getMemberInitials(member.name)}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-base-content mb-0.5">
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
    <!--  RECENT TRANSACTIONS                       -->
    <!-- ════════════════════════════════════════════ -->
    {#if recentTransactions.length > 0}
        <ResponsiveCard wrapperClass="space-y-0" class="p-0">
            <div class="flex items-center justify-between px-5 pt-[18px] pb-[14px] md:px-6">
                <p class="text-[0.63rem] font-bold tracking-widest uppercase text-base-content/50">
                    Recent Transactions
                </p>
                <span class="text-[0.75rem] font-semibold text-teal cursor-pointer">
                    See All &rarr;
                </span>
            </div>

            <div class="pb-1.5">
                {#each recentTransactions as txn, i (txn.id)}
                    {#if i > 0}
                        <hr class="border-base-content/10 mx-5 md:mx-6" />
                    {/if}

                    <div
                        class="flex items-center gap-3 px-5 py-3 md:px-6"
                        class:pb-4={i === recentTransactions.length - 1}>
                        <div
                            class="flex size-[38px] shrink-0 items-center justify-center rounded-xl text-base">
                            {#if txn.category?.decorations?.icon?.value}
                                <i class="iconify {txn.category.decorations.icon.value}"></i>
                            {:else}
                                <i class="iconify ph--receipt-bold"></i>
                            {/if}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold text-base-content mb-0.5">
                                {txn.description ?? 'Transaction'}
                            </p>
                            <p class="text-[0.73rem] text-base-content/50">
                                {DateTimeHelper.format(txn.transaction_date, 'date')}
                            </p>
                        </div>
                        <span class="mono shrink-0 text-sm font-medium {getTxnColor(txn.type)}">
                            {isInflow(txn) ? '+' : '-'}
                            {Formatter.currency(txn.amount, true)}
                        </span>
                    </div>
                {/each}
            </div>
        </ResponsiveCard>
    {/if}

    <!-- ════════════════════════════════════════════ -->
    <!--  DETAIL FIELDS (timestamps)                -->
    <!-- ════════════════════════════════════════════ -->
    <ResponsiveCard wrapperClass="space-y-5" class="p-2.5">
        <p class=" font-semibold tracking-widest uppercase text-base-content/50 text-xs">Details</p>

        <!-- Account number -->
        <div class="flex items-start gap-3">
            <i
                class="iconify size-5 text-base-content/50 shrink-0 mt-0.5 ph--identification-badge-bold"
            ></i>
            <div>
                <p class="text-xs text-base-content/50 mb-0.5">Account ID</p>
                <p class="font-mono text-sm text-base-content">#{account.id}</p>
            </div>
        </div>

        <hr class="border-base-content/10" />

        <!-- Created / Updated -->
        <div class="flex items-start justify-between gap-4">
            <div class="flex items-start gap-3">
                <i class="iconify size-5 text-base-content/50 shrink-0 mt-0.5 ph--clock-bold"></i>
                <div>
                    <p class="text-xs text-base-content/50 mb-0.5">Created</p>
                    <p class="text-sm text-base-content">
                        {DateTimeHelper.format(account.created_at, 'datetime')}
                    </p>
                </div>
            </div>
            {#if account.updated_at}
                <div class="text-right">
                    <p class="text-xs text-base-content/50 mb-0.5">Updated</p>
                    <p class="text-sm text-base-content">
                        {DateTimeHelper.format(account.updated_at, 'datetime')}
                    </p>
                </div>
            {/if}
        </div>
    </ResponsiveCard>
</div>
