<script lang="ts">
    import type { App } from '@wayfinder/types';

    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';

    import { accountSchema } from '@schema/account.schema';

    import { DataComposer } from '@utilities/data-composer';

    import DataList from '@components/data/data-list.svelte';
    import AccountAccessTypeBadge from '@components/module/account/account-access-type-badge.svelte';
    import AccountTypeBadge from '@components/module/account/account-type-badge.svelte';
    import ProviderTypeBadge from '@components/module/provider/provider-type-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    let { account }: { account: App.Models.Account } = $props();

    const details = $derived(
        DataComposer.from(accountSchema)
            .except(['type', 'access_type', 'name'])
            .toDataDisplay(account)
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <Button
                class="btn-circle btn-sm"
                color="light"
                href={AccountsController.index.url()}
                variant="ghost">
                <i class="iconify size-5 ph--arrow-left-bold"></i>
            </Button>
            <div>
                <h1 class="text-xl font-bold">{account.name}</h1>
                <div class="mt-1 flex items-center gap-1">
                    <AccountTypeBadge type={account.type} />
                    <AccountAccessTypeBadge type={account.access_type} />
                </div>
            </div>
        </div>
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountsController.edit.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--pencil-simple-bold"></i>
        </Button>
    </div>

    <Card wrapperClass="mb-4">
        <DataList data={details} />
    </Card>

    {#if account.provider}
        <Card wrapperClass="mb-4">
            <div class="flex items-center justify-between">
                <p class="font-semibold">{account.provider.name}</p>
                <ProviderTypeBadge type={account.provider.type} />
            </div>
        </Card>
    {/if}

    <div class="flex flex-col items-center py-10 text-base-content/40">
        <i class="iconify mb-2 size-10 ph--receipt-bold"></i>
        <p class="text-sm">Transactions will appear here</p>
    </div>
</div>
