<script lang="ts">
    /**
     * Visual-first implementation of docs/mockups/2026-09-25-finance-home.html.
     * Still rendering mockup demo data — real props get wired after visual sign-off.
     */

    import { Link } from '@inertiajs/svelte';
    import accounts from '@wayfinder/routes/accounts';
    import transactions from '@wayfinder/routes/transactions';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import ThemeSelector from '@components/ui/theme-selector.svelte';

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
            avatarClass: 'text-error text-2xl',
        },
        {
            name: 'PayPal Transfer',
            detail: 'Income • Jun 22',
            amount: '+ $2,500.00',
            amountClass: 'text-success',
            avatar: 'P',
            avatarClass: 'text-info text-2xl italic',
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

<HeaderContext>
    <div class="flex min-w-0 flex-1 items-center justify-between gap-3 pl-3 md:pl-0">
        <div class="min-w-0">
            <ThemeSelector />
            <!-- <p
                class="truncate text-xs font-medium text-secondary-content/60 md:text-base-content/60">
                {greeting},
            </p>
            <p
                class="truncate text-base font-bold tracking-tight text-secondary-content md:text-base-content">
                {user.name}
            </p> -->
        </div>
        <button
            class="relative grid size-10 shrink-0 place-items-center rounded-full bg-secondary-content/10 text-secondary-content transition hover:bg-secondary-content/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary md:bg-base-200 md:text-base-content"
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
                class="absolute top-0.5 right-0.5 grid size-4 place-items-center rounded-full bg-accent text-xs font-bold text-accent-content"
                >9+</span>
        </button>
    </div>
</HeaderContext>

<MobilePageLayout variant="2/5">
    {#snippet hero()}
        <section class="px-6 pt-6" aria-labelledby="total-balance">
            <p id="total-balance" class="text-base font-medium text-secondary-content/60">
                Total balance
            </p>
            <p class="mt-1 text-5xl leading-none font-semibold tracking-tighter tabular-nums">
                $367,289<span class="text-3xl font-medium text-secondary-content/60">.00</span>
            </p>
        </section>

        <section class="flex gap-3 px-6 pt-6" aria-label="Quick actions">
            <button
                class="flex h-12 flex-1 items-center justify-center gap-3 rounded-full bg-secondary-content/10 text-base font-semibold transition hover:bg-secondary-content/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                type="button">
                <span
                    class="grid size-7 place-items-center rounded-full border border-secondary-content/25">
                    <svg
                        class="size-4"
                        aria-hidden="true"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24">
                        <path
                            d="M7 17 17 7M8 7h9v9"
                            stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </span>
                Transfer
            </button>
            <button
                class="flex h-12 flex-1 items-center justify-center gap-3 rounded-full bg-secondary-content/10 text-base font-semibold transition hover:bg-secondary-content/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                type="button">
                <span
                    class="grid size-7 place-items-center rounded-full border border-secondary-content/25">
                    <svg
                        class="size-4"
                        aria-hidden="true"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        viewBox="0 0 24 24">
                        <path
                            d="M17 7 7 17M16 17H7V8"
                            stroke-linecap="round"
                            stroke-linejoin="round" />
                    </svg>
                </span>
                Request
            </button>
            <button
                class="grid size-12 place-items-center rounded-full bg-secondary-content/10 transition hover:bg-secondary-content/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                aria-label="More actions"
                type="button">
                <span class="grid grid-cols-2 gap-1">
                    <span class="size-1.5 rounded-full border border-secondary-content"></span>
                    <span class="size-1.5 rounded-full border border-secondary-content"></span>
                    <span class="size-1.5 rounded-full border border-secondary-content"></span>
                    <span class="size-1.5 rounded-full border border-secondary-content"></span>
                </span>
            </button>
        </section>

        <section class="px-6 pt-6" aria-labelledby="cards-heading">
            <div class="flex items-center justify-between">
                <h2 id="cards-heading" class="text-lg font-semibold">My Cards</h2>
                <Link
                    class="flex items-center gap-1 text-base text-secondary-content/60 transition hover:text-secondary-content focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
                    href={accounts.create().url}>
                    <span
                        class="grid size-4 place-items-center rounded-full border border-secondary-content/45 text-xs leading-none"
                        >+</span>
                    Add
                </Link>
            </div>

            <div class="mt-4 flex gap-4 overflow-x-auto" aria-label="Bank cards">
                {#each cards as card (card.number)}
                    {#if card.variant === 'primary'}
                        <section
                            class="card-pattern w-78.75 shrink-0 snap-start rounded-3xl bg-primary px-5 py-5 text-primary-content shadow-lg"
                            aria-label="Primary card ending in 3281">
                            <div class="h-5 w-15 bg-primary-content"></div>
                            <p class="mt-6 text-2xl font-bold tracking-widest">
                                {card.number}
                            </p>
                            <div class="mt-5 flex items-end justify-between">
                                <div>
                                    <p class="text-sm font-medium">Balance</p>
                                    <p class="text-3xl font-bold tracking-tighter">
                                        {card.balance}<span class="text-lg font-medium"
                                            >{card.cents}</span>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium">{card.meta?.label}</p>
                                    <p class="text-lg font-bold">{card.meta?.value}</p>
                                </div>
                            </div>
                        </section>
                    {:else}
                        <section
                            class="w-52.5 shrink-0 snap-start rounded-3xl bg-accent px-5 py-5 text-accent-content"
                            aria-label="Secondary card">
                            <div class="h-5 w-15 bg-accent-content"></div>
                            <p class="mt-7 text-xl font-bold tracking-widest">
                                {card.number}
                            </p>
                            <p class="mt-6 text-sm font-medium">Balance</p>
                            <p class="text-2xl font-bold tracking-tighter">
                                {card.balance}<span class="text-base font-medium"
                                    >{card.cents}</span>
                            </p>
                        </section>
                    {/if}
                {/each}
            </div>
        </section>
    {/snippet}

    <!-- <div class="mx-auto h-1.5 w-13 rounded-full bg-base-content/40"></div> -->
    <div class="mt-4 flex items-center justify-between">
        <h2 id="transactions-heading" class="text-lg font-bold tracking-tight">
            Latest Transactions
        </h2>
        <Link
            class="text-sm font-medium text-base-content/50 transition hover:text-base-content focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
            href={transactions.index().url}>
            See all
        </Link>
    </div>
    <ul class="mt-3 divide-y divide-base-content/10">
        {#each recentTransactions as transaction (transaction.name)}
            <li class="flex items-center gap-3 py-3">
                <span
                    class="grid size-12 place-items-center rounded-full bg-base-300 font-black {transaction.avatarClass}">
                    {transaction.avatar}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-base font-bold">{transaction.name}</span>
                    <span class="mt-0.5 block text-sm text-base-content/50"
                        >{transaction.detail}</span>
                </span>
                <span class="tracking-snug text-base font-bold {transaction.amountClass}">
                    {transaction.amount}
                </span>
            </li>
        {/each}
    </ul>
</MobilePageLayout>

<style>
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
