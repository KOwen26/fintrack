<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import DataList from '@components/data/data-list.svelte';
    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import PageSection from '@components/layouts/page-section.svelte';
    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import ProviderTypeBadge from '@components/module/provider/provider-type-badge.svelte';
    import TransactionList from '@components/module/transaction/transaction-list.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    let { account }: { account: App.Models.Account } = $props();
</script>

<DashboardPageHeader title="">
    <div class="space-y-1.5">
        <h1 class="text-xl font-bold">{account.name}</h1>
        <div class="flex items-center gap-1.5">
            <AccountTypeBadge type={account.type} />
            <AccountAccessTypeBadge type={account.access_type} />
            <Badge>{account?.provider?.name}</Badge>
        </div>
    </div>

    {#snippet actions()}
        <Button
            color="light"
            href={AccountController.edit.url({ account: account.id })}
            variant="outline">
            <i class="iconify size-5 ph--pencil-simple-bold"></i>
            Edit
        </Button>
    {/snippet}
</DashboardPageHeader>

<PageSection breakMargin>
    {#if !account?.transactions}
        <EmptyItemPlaceholder label="No Transaction Yet" />
    {:else}
        <TransactionList transactions={account.transactions} cardProps={{ withoutAccount: true }} />
    {/if}
</PageSection>
