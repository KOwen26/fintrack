<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import Button from '@components/ui/button.svelte';

    interface Props extends RestProps {
        account: App.Models.Account;
        hideActions?: boolean;
        hideEdit?: boolean;
    }

    let {
        account,
        hideActions = false,
        hideEdit = false,
        class: _class,
        children,
        ...props
    }: Props = $props();

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

    // ── Quick actions by account type ────────────────────────────
    interface ActionItem {
        icon: string;
        label: string;
    }

    const actionRegistry: Record<string, ActionItem> = {
        transact: { icon: 'ph--arrows-left-right-bold', label: 'Transact' },
        transfer: { icon: 'ph--arrow-up-right-bold', label: 'Transfer' },
        report: { icon: 'ph--chart-bar-bold', label: 'Report' },
        connect: { icon: 'ph--link-bold', label: 'Connect' },
    };

    const accountActions: Record<string, string[]> = {
        [AccountType.DebitAccount]: ['transact', 'transfer', 'report', 'connect'],
        [AccountType.CreditCard]: ['transact', 'transfer', 'report', 'connect'],
        [AccountType.CashWallet]: ['transact', 'transfer', 'report'],
        [AccountType.EWallet]: ['transact', 'transfer', 'report', 'connect'],
        [AccountType.Investment]: ['transact', 'transfer', 'report'],
    };

    const quickActions = $derived(
        (accountActions[account.type] ?? []).map((key) => actionRegistry[key])
    );
</script>

<!-- ════════════════════════════════════════════ -->
<!--  HERO CARD                                  -->
<!-- ════════════════════════════════════════════ -->
<div class="overflow-hidden rounded-xl shadow-xs">
    <div
        style:background={bgColor}
        style:color={colorObj?.text_color}
        class="relative overflow-hidden p-5 md:px-6 md:pb-5">
        <!-- Decorative circles -->
        <div class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-white/5">
        </div>
        <div
            class="pointer-events-none absolute right-10 -bottom-8 size-28 rounded-full bg-white/5">
        </div>

        <!-- Header row -->
        <div class="relative z-1 flex items-center gap-2">
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 md:size-9">
                {#if heroIcon}
                    <i class="iconify size-5 text-current md:size-6 {heroIcon}"></i>
                {:else}
                    <i class="iconify size-5 text-current ph--bank-bold md:size-6"></i>
                {/if}
            </div>
            <div class="flex-1">
                <div class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-current md:text-base">
                        {account.name}
                    </span>
                    <span
                        class="rounded-full bg-white/20 px-1.5 py-0.5 text-[9px] font-medium tracking-wider text-current/85 uppercase">
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
                    {Formatter.currency(account.current_balance ?? account.initial_balance ?? 0)}
                {/if}
            </p>
        </div>

        <!-- Quick actions — horizontal scroll on mobile, static row on desktop -->
        {#if !hideActions && quickActions.length > 0}
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
        {/if}
    </div>

    <!-- Detail badges row — white background -->
    {#if !hideEdit}
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
    {/if}
</div>
