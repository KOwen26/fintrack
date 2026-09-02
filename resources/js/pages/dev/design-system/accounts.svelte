<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
    import AccountType from '@wayfinder/App/Enums/AccountType';
    import ProviderStatus from '@wayfinder/App/Enums/ProviderStatus';
    import ProviderType from '@wayfinder/App/Enums/ProviderType';

    import { setBreadcrumbItems } from '@utilities/global-states.svelte';

    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountBadge from '@components/module/account/account-badge.svelte';
    import AccountCard from '@components/module/account/account-card.svelte';
    import AccountInfo from '@components/module/account/account-info.svelte';
    import AccountList from '@components/module/account/account-list.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import AccountsSummaryCard from '@components/module/account/accounts-summary-card.svelte';
    import BaseAccountCard from '@components/module/account/base-account-card.svelte';

    setBreadcrumbItems([{ title: 'Dev' }, { title: 'Design System' }, { title: 'Accounts' }]);

    const provider: App.Models.Provider = {
        id: 1,
        name: 'BCA',
        slug: 'bca',
        logo_url: null,
        type: ProviderType.Bank,
        status: ProviderStatus.Active,
        decorations: { icon: 'banknote-2', color: 'blue-600' },
        created_at: '2026-01-01T00:00:00.000000Z',
        updated_at: '2026-01-01T00:00:00.000000Z',
    };

    const savingsAccount: App.Models.Account = {
        id: 1,
        owner_id: 1,
        provider_id: 1,
        name: 'BCA Savings',
        type: AccountType.DebitAccount,
        access_type: AccountAccessType.Personal,
        current_balance: 12_500_000,
        initial_balance: 10_000_000,
        decorations: { icon: 'money-bag', color: 'emerald-600' },
        archived_at: null,
        created_at: '2026-01-15T00:00:00.000000Z',
        updated_at: '2026-09-01T00:00:00.000000Z',
        deleted_at: null,
        provider,
    };

    const jointWallet: App.Models.Account = {
        id: 2,
        owner_id: 1,
        provider_id: null,
        name: 'Family Wallet',
        type: AccountType.CashWallet,
        access_type: AccountAccessType.Joint,
        current_balance: 3_450_000,
        initial_balance: 2_000_000,
        decorations: { icon: 'wallet', color: 'violet-600' },
        archived_at: null,
        created_at: '2026-03-02T00:00:00.000000Z',
        updated_at: '2026-09-01T00:00:00.000000Z',
        deleted_at: null,
        provider: null,
    };

    const summary = {
        total_balance: 20_800_000,
        available_balance: 15_950_000,
        investment_balance: 4_850_000,
    };
</script>

<div class="space-y-8">
    <h1>Design System — Account Card</h1>

    <div class="space-y-6">
        <section>
            <h2>Variant: default</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Rendered through the composed AccountCard — decorations drive the hero color and
                icon, falling back to the type default. Provider and joint access show in the
                subtitle row.
            </p>
            <div class="grid gap-5 md:grid-cols-2">
                <AccountCard account={savingsAccount} />
                <AccountCard account={jointWallet} />
            </div>
        </section>

        <section>
            <h2>Variant: skeleton</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                BaseAccountCard with variant="skeleton" — loading placeholder mirroring the card
                layout while accounts are being fetched.
            </p>
            <div class="grid gap-5 md:grid-cols-2">
                <BaseAccountCard variant="skeleton" />
            </div>
        </section>

        <section>
            <h2>Variant: create</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                BaseAccountCard with variant="create" — dashed call-to-action (as on the accounts
                index). Pass children to replace the default "Add Account" content.
            </p>
            <div class="grid gap-5 md:grid-cols-2">
                <BaseAccountCard variant="create" />
                <BaseAccountCard variant="create">
                    <div class="text-center">
                        <i
                            class="mx-auto mb-1 iconify block size-5 text-base-content/50 solar--wallet-bold-duotone"
                        ></i>
                        <span class="text-sm font-medium text-base-content/50">Open E-Wallet</span>
                    </div>
                </BaseAccountCard>
            </div>
        </section>

        <section>
            <h2>Accounts Summary Card</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Brand hero for the accounts index — primary gradient with glow blobs. Stacks on
                mobile; on lg screens it lays out horizontally with divided sub-stats.
            </p>
            <AccountsSummaryCard {summary} />
        </section>

        <section>
            <h2>Account Type Badge</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                One color per account type. The icon prop accepts "show" (default), "hide", or
                "only".
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <AccountTypeBadge type={AccountType.DebitAccount} />
                <AccountTypeBadge type={AccountType.CreditCard} />
                <AccountTypeBadge type={AccountType.CashWallet} />
                <AccountTypeBadge type={AccountType.EWallet} />
                <AccountTypeBadge type={AccountType.Investment} />
                <span class="text-base-content/40">|</span>
                <AccountTypeBadge icon="hide" type={AccountType.DebitAccount} />
                <AccountTypeBadge icon="only" type={AccountType.EWallet} />
            </div>
        </section>

        <section>
            <h2>Access Type Badge</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Personal and Joint access — soft variant badges, same icon modes as the type badge.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <AccountAccessTypeBadge type={AccountAccessType.Personal} />
                <AccountAccessTypeBadge type={AccountAccessType.Joint} />
                <span class="text-base-content/40">|</span>
                <AccountAccessTypeBadge icon="only" type={AccountAccessType.Joint} />
            </div>
        </section>

        <section>
            <h2>Account Badge</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Decoration-colored account name chip. labelOnly drops the chip background and tints
                the text instead.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <AccountBadge account={savingsAccount} />
                <AccountBadge account={jointWallet} />
                <span class="text-base-content/40">|</span>
                <AccountBadge account={savingsAccount} labelOnly />
                <AccountBadge account={jointWallet} labelOnly />
            </div>
        </section>

        <section>
            <h2>Account Info</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Icon chip with decoration colors plus the label-only name badge and type badge.
                reverse flips the layout; asLink wraps it in a link to the account show page.
            </p>
            <div class="flex flex-wrap items-start gap-10">
                <AccountInfo account={savingsAccount} />
                <AccountInfo account={jointWallet} />
                <AccountInfo account={savingsAccount} reverse />
            </div>
        </section>

        <section>
            <h2>Account List</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                Toggleable list/grid of cards linking to the show page, with an empty state and
                create CTA when no accounts exist.
            </p>
            <AccountList accounts={[savingsAccount, jointWallet]} />
        </section>

        <section>
            <h2>Account List — Empty</h2>
            <hr class="mt-2 mb-4" />
            <p class="mb-4 text-sm text-base-content/60">
                EmptyItemPlaceholder with a create CTA when the account list has no rows.
            </p>
            <AccountList accounts={[]} />
        </section>
    </div>
</div>
