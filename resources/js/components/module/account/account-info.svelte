<script lang="ts">
    import type { Models } from '@type/type';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';
    import { Link } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountBadge from '@components/module/account/account-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';

    interface Props {
        account: Models.Account;
        reverse?: boolean;
        asLink?: boolean;
    }

    let { account, asLink = false, reverse = false }: Props = $props();

    const iconClass = $derived(
        account?.decorations?.icon
            ? (getDecorationIcon(account.decorations.icon)?.value ?? 'ph--bank-bold')
            : 'ph--bank-bold'
    );

    const colorValue = $derived(
        account?.decorations?.color
            ? getDecorationColor(account.decorations.color)?.value
            : undefined
    );
</script>

{#if asLink}
    <Link href={AccountController.show.url(account?.id)}>
        {@render Item()}
    </Link>
{:else}
    {@render Item()}
{/if}

{#snippet Item()}
    <div class="flex {reverse ? 'flex-row-reverse' : ''} items-center gap-3">
        <div
            style:background={colorValue ? `${colorValue}20` : undefined}
            style:color={colorValue ?? undefined}
            class="size-10 rounded-xl bg-base-content/10 flex items-center justify-center shrink-0">
            <i class="iconify size-5 {iconClass}"></i>
        </div>
        <div class="grow">
            <AccountBadge {account} labelOnly />
            <p class="my-1"></p>
            <AccountTypeBadge type={account?.type} />
        </div>
    </div>
{/snippet}
