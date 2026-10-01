<script lang="ts">
    import type { Models } from '@type/type';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { Link } from '@inertiajs/svelte';
    import { AccountType, AccountTypeMeta } from '@wayfinder/App/Enums/AccountType';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';

    interface Props {
        account: Models.Account;
        reverse?: boolean;
        asLink?: boolean;
    }

    let { account, asLink = false, reverse = false }: Props = $props();

    /* Type-driven icon fallback — the decoration icon wins, then the account
       type's icon (same chain as account-card). */
    const typeIcons: Record<string, string> = {
        [AccountType.DebitAccount]: 'solar--banknote-2-bold-duotone',
        [AccountType.CreditCard]: 'solar--card-bold-duotone',
        [AccountType.CashWallet]: 'solar--wallet-bold-duotone',
        [AccountType.EWallet]: 'solar--smartphone-bold-duotone',
        [AccountType.Investment]: 'solar--graph-bold-duotone',
    };

    const hex = $derived(getDecorationColor(account?.decorations?.color)?.hex);

    const visual = $derived({
        icon:
            getDecorationIcon(account?.decorations?.icon)?.value ??
            typeIcons[account?.type ?? ''] ??
            'solar--banknote-2-bold-duotone',
        background: hex ? `${hex}20` : undefined,
        color: hex ?? undefined,
    });
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
        <DecorationBadge size="md" {...visual} />
        <div class="grow">
            <h6 class="text-sm font-medium tracking-wide text-base-content/80">
                {AccountTypeMeta?.[account.type]?.label}
            </h6>
            <p class="text-sm font-bold">
                {account.name}
            </p>
        </div>
    </div>
{/snippet}
