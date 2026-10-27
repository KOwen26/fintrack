<script lang="ts">
    import type { Models } from '@type/type';

    import { Link } from '@inertiajs/svelte';
    import { AccountType, AccountTypeMeta } from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import DecorationBadge from '@components/module/decoration-badge.svelte';

    interface Props {
        account: Models.Account;
        reverse?: boolean;
        asLink?: boolean;
    }

    let { account, asLink = false, reverse = false }: Props = $props();

    /* Type-driven icon fallback — passed as the badge's fallback icon; the
       account's decoration icon wins inside the badge (same chain as
       account-card). */
    const typeIcons: Record<string, string> = {
        [AccountType.DebitAccount]: 'solar--banknote-2-bold-duotone',
        [AccountType.CreditCard]: 'solar--card-bold-duotone',
        [AccountType.CashWallet]: 'solar--wallet-bold-duotone',
        [AccountType.EWallet]: 'solar--smartphone-bold-duotone',
        [AccountType.Investment]: 'solar--graph-bold-duotone',
    };
</script>

{#if asLink}
    <Link href={AccountController.show.url(account?.id)}>
        {@render Item()}
    </Link>
{:else}
    {@render Item()}
{/if}

{#snippet Item()}
    <div
        class="flex {reverse
            ? 'flex-row-reverse'
            : ''} items-center gap-3 rounded-lg border border-border p-2">
        <DecorationBadge
            size="md"
            decorations={account?.decorations}
            icon={typeIcons[account?.type ?? ''] ?? 'solar--banknote-2-bold-duotone'} />
        <div class="grow">
            <h6 class="text-sm font-medium tracking-wide text-foreground/70">
                {AccountTypeMeta?.[account.type]?.label}
            </h6>
            <p class="text-sm font-bold">
                {account.name}
            </p>
        </div>
    </div>
{/snippet}
