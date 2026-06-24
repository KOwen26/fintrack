<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';
    import AccountSelect from '@components/ui/forms/account-select.svelte';

    let {
        account,
        categories,
        accounts,
    }: {
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts: App.Models.Account[];
    } = $props();

    let activeTab = $state<'income' | 'expense' | 'transfer'>('expense');
</script>

<div class="p-4">
    <Tabs bind:value={activeTab}>
        <TabsList>
            <TabsTrigger value="income">Income</TabsTrigger>
            <TabsTrigger value="expense">Expense</TabsTrigger>
            <TabsTrigger value="transfer">Transfer</TabsTrigger>
        </TabsList>
    </Tabs>

    <AccountSelect />

    {#key activeTab}
        <div class="mt-4">
            <TransactionForm
                {account}
                {accounts}
                {categories}
                onCancel={() =>
                    router.visit(TransactionController.index.url({ account: account.id }))}
                type={activeTab} />
        </div>
    {/key}
</div>
