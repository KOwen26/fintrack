<script lang="ts">
    import type { Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import TransferForm from '@components/module/transaction/transfer-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';

    let {
        categories,
        accounts,
    }: {
        categories: Models.Category[];
        accounts: Models.Account[];
    } = $props();

    let activeTab = $state<App.Enums.TransactionType>(TransactionType.Expense);

    const cancel = () => router.visit(TransactionController.index.url());
</script>

<DashboardPageHeader title="New Transaction" />

<PageSection>
    <Tabs bind:value={activeTab}>
        <TabsList>
            <TabsTrigger value={TransactionType.Income}>Income</TabsTrigger>
            <TabsTrigger value={TransactionType.Expense}>Expense</TabsTrigger>
            <TabsTrigger value={TransactionType.Transfer}>Transfer</TabsTrigger>
        </TabsList>
    </Tabs>

    {#key activeTab}
        <div class="mt-4">
            {#if activeTab === TransactionType.Transfer}
                <TransferForm {accounts} onCancel={cancel} />
            {:else}
                <TransactionForm {accounts} {categories} onCancel={cancel} type={activeTab} />
            {/if}
        </div>
    {/key}
</PageSection>
