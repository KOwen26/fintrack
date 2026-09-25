<script lang="ts">
    /**
     * Visual-first implementation of docs/mockups/2026-09-25-finance-home.html.
     * Still rendering mockup demo data — real props get wired after visual sign-off.
     */

    import { Link, setLayoutProps } from '@inertiajs/svelte';
    import accounts from '@wayfinder/routes/accounts';
    import transactions from '@wayfinder/routes/transactions';
    import { onMount } from 'svelte';

    import HeaderContext from '@components/navigation/header-context.svelte';

    /* ── Layout shell ───────────────────────────────────── */

    // Extend the mobile shell (header zone included) with the screen's color so
    // the context bar reads as blank space belonging to this page.
    setLayoutProps({ mobileShellClass: 'bg-[#0b0b0d] pt-9' });

    // Preserved-state visits keep layout props — clear ours on unmount.
    onMount(() => () => setLayoutProps({ mobileShellClass: undefined }));

    /* ── Demo data (from the mockup) ─────────────────────── */

    const user = { name: 'Jhonny Cantona' };

    interface DemoCard {
        number: string;
        balance: string;
        cents: string;
        meta?: { label: string; value: string };
        variant: 'primary' | 'secondary';
    }

    const cards: DemoCard[] = [
        {
            number: '**** **** **** 3281',
            balance: '$218,000',
            cents: '.00',
            meta: { label: 'Valid Thru', value: '06/27' },
            variant: 'primary',
        },
        {
            number: '**** 1280',
            balance: '$149,289',
            cents: '.00',
            variant: 'secondary',
        },
    ];

    interface DemoTransaction {
        name: string;
        detail: string;
        amount: string;
        amountClass: string;
        avatar: string;
        avatarClass: string;
    }

    const recentTransactions: DemoTransaction[] = [
        {
            name: 'Netflix',
            detail: 'Entertainment • Jun 23',
            amount: '− $42.00',
            amountClass: '',
            avatar: 'N',
            avatarClass: 'text-[#e40914] text-[24px]',
        },
        {
            name: 'PayPal Transfer',
            detail: 'Income • Jun 22',
            amount: '+ $2,500.00',
            amountClass: 'text-[#40a77b]',
            avatar: 'P',
            avatarClass: 'text-[#1665ad] text-[23px] italic',
        },
    ];

    /* ── Derived ─────────────────────────────────────────── */

    const greeting = $derived.by(() => {
        const hour = new Date().getHours();

        if (hour < 12) return 'Good Morning';
        if (hour < 18) return 'Good Afternoon';

        return 'Good Evening';
    });
</script>

<svelte:head>
    <title>Dashboard 2</title>
    <link href="https://fonts.googleapis.com" rel="preconnect" />
    <link crossorigin="anonymous" href="https://fonts.gstatic.com" rel="preconnect" />
    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet" />
</svelte:head>

<HeaderContext>
    <div class="flex min-w-0 flex-1 items-center justify-between gap-3 pl-3 md:pl-0">
        <div class="min-w-0">
            <p class="truncate text-[12px] font-medium text-white/55 md:text-base-content/60">
                {greeting},
            </p>
            <p
                class="truncate text-[16px] font-bold tracking-[-0.02em] text-white md:text-base-content">
                {user.name}
            </p>
        </div>
        <button
            class="relative grid size-10 shrink-0 place-items-center rounded-full bg-white/[0.07] text-white transition hover:bg-white/[0.14] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246] md:bg-base-200 md:text-base-content"
            aria-label="Notifications"
            type="button">
            <svg
                class="size-5"
                aria-hidden="true"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                viewBox="0 0 24 24">
                <path
                    d="M18 9a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"
                    stroke-linecap="round"
                    stroke-linejoin="round" />
            </svg>
            <span
                class="absolute top-0.5 right-0.5 grid size-4 place-items-center rounded-full bg-[#ef6c69] text-[9px] font-bold"
                >9+</span>
        </button>
    </div>
</HeaderContext>

<article
    class="finance-screen relative flex min-h-screen w-full flex-col overflow-hidden bg-[#0b0b0d] text-white antialiased"
    aria-label="Finance home screen">
    <section class="px-6 pt-6" aria-labelledby="total-balance">
        <p id="total-balance" class="text-[16px] font-medium text-white/55">Total balance</p>
        <p
            class="mt-1 text-[42px] leading-none font-semibold tracking-[-0.07em] tabular-nums sm:text-[45px]">
            $367,289<span class="text-[28px] font-medium text-white/55">.00</span>
        </p>
    </section>

    <section class="flex gap-3 px-6 pt-6" aria-label="Quick actions">
        <button
            class="flex h-12 flex-1 items-center justify-center gap-3 rounded-full bg-[#1b1b1e] text-[15px] font-semibold transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246]"
            type="button">
            <span class="grid size-7 place-items-center rounded-full border border-white/20">
                <svg
                    class="size-4"
                    aria-hidden="true"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24">
                    <path d="M7 17 17 7M8 7h9v9" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            Transfer
        </button>
        <button
            class="flex h-12 flex-1 items-center justify-center gap-3 rounded-full bg-[#1b1b1e] text-[15px] font-semibold transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246]"
            type="button">
            <span class="grid size-7 place-items-center rounded-full border border-white/20">
                <svg
                    class="size-4"
                    aria-hidden="true"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    viewBox="0 0 24 24">
                    <path d="M17 7 7 17M16 17H7V8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            Request
        </button>
        <button
            class="grid size-12 place-items-center rounded-full bg-[#1b1b1e] transition hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246]"
            aria-label="More actions"
            type="button">
            <span class="grid grid-cols-2 gap-1">
                <span class="size-1.5 rounded-full border border-white"></span>
                <span class="size-1.5 rounded-full border border-white"></span>
                <span class="size-1.5 rounded-full border border-white"></span>
                <span class="size-1.5 rounded-full border border-white"></span>
            </span>
        </button>
    </section>

    <section class="px-6 pt-6" aria-labelledby="cards-heading">
        <div class="flex items-center justify-between">
            <h2 id="cards-heading" class="text-[17px] font-semibold">My Cards</h2>
            <Link
                class="flex items-center gap-1 text-[15px] text-white/55 transition hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246]"
                href={accounts.create().url}>
                <span
                    class="grid size-4 place-items-center rounded-full border border-white/45 text-[12px] leading-none"
                    >+</span>
                Add
            </Link>
        </div>

        <div class="mt-4 flex gap-4 overflow-x-auto" aria-label="Bank cards">
            {#each cards as card (card.number)}
                {#if card.variant === 'primary'}
                    <section
                        class="card-pattern w-[315px] shrink-0 snap-start rounded-[27px] bg-[#a8f246] px-5 py-5 text-[#101012] shadow-lg"
                        aria-label="Primary card ending in 3281">
                        <div class="h-5 w-15 bg-[#101012]"></div>
                        <p class="mt-6 text-[22px] font-bold tracking-[0.12em]">
                            {card.number}
                        </p>
                        <div class="mt-5 flex items-end justify-between">
                            <div>
                                <p class="text-[13px] font-medium">Balance</p>
                                <p class="text-[27px] font-bold tracking-[-0.08em]">
                                    {card.balance}<span class="text-[17px] font-medium"
                                        >{card.cents}</span>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-[13px] font-medium">{card.meta?.label}</p>
                                <p class="text-[17px] font-bold">{card.meta?.value}</p>
                            </div>
                        </div>
                    </section>
                {:else}
                    <section
                        class="w-[210px] shrink-0 snap-start rounded-[27px] bg-[#b7b8ff] px-5 py-5 text-[#101012]"
                        aria-label="Secondary card">
                        <div class="h-5 w-15 bg-[#101012]"></div>
                        <p class="mt-7 text-[20px] font-bold tracking-[0.12em]">
                            {card.number}
                        </p>
                        <p class="mt-6 text-[13px] font-medium">Balance</p>
                        <p class="text-[25px] font-bold tracking-[-0.08em]">
                            {card.balance}<span class="text-[16px] font-medium">{card.cents}</span>
                        </p>
                    </section>
                {/if}
            {/each}
        </div>
    </section>

    <section
        class="relative mt-6 min-h-screen flex-1 rounded-t-[28px] bg-white px-6 pt-3 pb-8 text-[#101012]"
        aria-labelledby="transactions-heading">
        <!-- <div class="mx-auto h-1.5 w-13 rounded-full bg-black/40"></div> -->
        <div class="mt-4 flex items-center justify-between">
            <h2 id="transactions-heading" class="text-[18px] font-bold tracking-[-0.03em]">
                Latest Transactions
            </h2>
            <Link
                class="text-[14px] font-medium text-black/50 transition hover:text-black focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#a8f246]"
                href={transactions.index().url}>
                See all
            </Link>
        </div>
        <ul class="mt-3 divide-y divide-black/15">
            {#each recentTransactions as transaction (transaction.name)}
                <li class="flex items-center gap-3 py-3">
                    <span
                        class="grid size-12 place-items-center rounded-full bg-[#ececee] font-black {transaction.avatarClass}">
                        {transaction.avatar}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[16px] font-bold">{transaction.name}</span>
                        <span class="mt-0.5 block text-[13px] text-black/50"
                            >{transaction.detail}</span>
                    </span>
                    <span
                        class="text-[15px] font-bold tracking-[-0.04em] {transaction.amountClass}">
                        {transaction.amount}
                    </span>
                </li>
            {/each}
        </ul>
    </section>
</article>

<style>
    .finance-screen {
        font-family:
            'Manrope',
            ui-sans-serif,
            system-ui,
            -apple-system,
            'Segoe UI',
            sans-serif;
    }

    .screen-shadow {
        box-shadow: 0 36px 90px rgb(9 12 8 / 35%);
    }

    .card-pattern {
        background-image:
            radial-gradient(circle at 78% 100%, rgb(255 255 255 / 16%) 0 19%, transparent 19.3%),
            radial-gradient(circle at 77% 100%, rgb(255 255 255 / 10%) 0 37%, transparent 37.3%),
            linear-gradient(133deg, rgb(255 255 255 / 10%), transparent 42%);
    }
</style>
