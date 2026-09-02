<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import BaseAccountCard from '@components/module/account/base-account-card.svelte';
    import Link from '@components/ui/link.svelte';

    interface Props extends RestProps {
        account: App.Models.Account;
        hideActions?: boolean;
        hideEdit?: boolean;
        hideFooter?: boolean;
    }

    let {
        account,
        hideActions = false,
        hideEdit = false,
        hideFooter = false,
        class: _class,
        children,
        ...props
    }: Props = $props();

    // ── Decoration-driven colors ───────────────────────────────
    const colorObj = $derived(getDecorationColor(account.decorations?.color));
    const iconObj = $derived(getDecorationIcon(account.decorations?.icon));
    const bgColor = $derived(colorObj?.oklch);

    // ── Type-driven defaults (icon source: decoration first, then type) ──
    const typeIcons: Record<string, string> = {
        [AccountType.DebitAccount]: 'solar--banknote-2-bold-duotone',
        [AccountType.CreditCard]: 'solar--card-bold-duotone',
        [AccountType.CashWallet]: 'solar--wallet-bold-duotone',
        [AccountType.EWallet]: 'solar--smartphone-bold-duotone',
        [AccountType.Investment]: 'solar--graph-bold-duotone',
    };

    const typeLabels: Record<string, string> = {
        [AccountType.DebitAccount]: 'Debit',
        [AccountType.CreditCard]: 'Credit Card',
        [AccountType.CashWallet]: 'Cash',
        [AccountType.EWallet]: 'E-Wallet',
        [AccountType.Investment]: 'Investment',
    };

    const heroIcon = $derived<string>(
        iconObj?.value ?? typeIcons[account.type] ?? 'solar--banknote-2-bold-duotone'
    );
    const typeLabel = $derived(typeLabels[account.type] ?? account.type);

    const providerName = $derived<string | undefined>(
        (account.provider as { name?: string } | null)?.name
    );
    const isJoint = $derived(account.access_type === 'joint');

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
        transact: { icon: 'solar--transfer-horizontal-bold-duotone', label: 'Transact' },
        transfer: { icon: 'solar--arrow-right-up-line-duotone', label: 'Transfer' },
        report: { icon: 'solar--chart-2-bold-duotone', label: 'Report' },
        connect: { icon: 'solar--link-bold-duotone', label: 'Connect' },
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

<BaseAccountCard
    class={_class}
    bodyClass="flex flex-col justify-around gap-3 p-3 md:p-6"
    background={bgColor}
    footer={!hideFooter ? footer : undefined}
    textColor={colorObj?.text_color}
    variant="default">
    <!-- Decorative circles -->
    <div class="pointer-events-none absolute -top-12 -right-12 size-44 rounded-full bg-white/10">
    </div>
    <div class="pointer-events-none absolute right-10 -bottom-8 size-28 rounded-full bg-white/5">
    </div>

    <!-- Header row -->
    <div class="relative z-1 flex items-center gap-2">
        <div
            class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 md:size-9">
            <i class="iconify size-5 text-current md:size-6 {heroIcon}"></i>
        </div>
        <div class="flex-1">
            <div class="flex items-center gap-1.5">
                <span class="text-sm font-semibold text-current md:text-base">
                    {account.name}
                </span>
                <span
                    class="rounded-full bg-white/20 px-1.5 py-0.5 text-[9px] font-medium tracking-wider text-current/85 uppercase">
                    {typeLabel}
                </span>
            </div>
            <div class="flex items-center gap-2 text-xs text-current/70">
                {#if providerName}
                    <span>{providerName}</span>
                {/if}
                {#if providerName && isJoint}
                    <span>•</span>
                {/if}
                {#if isJoint}
                    <span>Joint</span>
                {/if}
            </div>
        </div>
        {@render editButton()}
        <!--
            <button
                class="flex size-8 cursor-pointer items-center justify-center rounded-lg border-none bg-white/10 transition-colors hover:bg-white/15"
                aria-label="Toggle balance visibility"
                onclick={toggleBalance}>
                <i
                    class="iconify size-4 text-white/80 {balanceHidden
                        ? 'solar--eye-closed-bold-duotone'
                        : 'solar--eye-bold-duotone'}">
                </i>
            </button>
        -->
    </div>

    <!-- Balance -->
    <div class="relative z-1">
        <div
            class="flex items-center gap-1.5 text-sm font-medium tracking-wide text-current/70 uppercase">
            Current Balance
        </div>
        <p class="text-2xl font-bold tracking-tight text-current md:text-4xl lg:text-5xl">
            {#if balanceHidden}
                ••••••
            {:else}
                {Formatter.currency(account.current_balance ?? 0)}
            {/if}
        </p>
    </div>

    <!-- Quick actions — horizontal scroll on mobile, static row on desktop -->
    {#if !hideActions && quickActions.length > 0}
        <div
            style="scrollbar-width: none; -ms-overflow-style: none;"
            class="relative z-1 -mx-5 overflow-x-auto px-5 pb-1 md:mx-0 md:px-0">
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
</BaseAccountCard>

{#snippet footer()}
    {#if !hideFooter}
        <!-- Detail badges row — white background -->
        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-t border-base-300 bg-card px-5 py-3 text-xs">
            {#if account?.account_number}
                <span class="flex items-center gap-1">
                    <i class="iconify size-4 text-base-content/80 solar--user-id-line-duotone"></i>
                    <span class="text-base-content/80">{account?.account_number}</span>
                </span>
            {/if}
            {#if account.created_at}
                <span class="flex items-center gap-1">
                    <i class="iconify size-4 text-base-content/80 solar--calendar-line-duotone"></i>
                    <span class="text-base-content/80"
                        >Since {DateTimeHelper.format(account.created_at, 'date')}</span>
                </span>
            {/if}
        </div>
    {/if}
{/snippet}

{#snippet editButton()}
    {#if !hideEdit}
        <Link
            class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-white/15 text-current transition-colors hover:bg-white/25 md:order-last md:ml-auto"
            aria-label="Edit account"
            href={AccountController.edit.url({ account: account.id })}>
            <i class="iconify size-4 solar--pen-new-square-line-duotone"></i>
        </Link>
    {/if}
{/snippet}
