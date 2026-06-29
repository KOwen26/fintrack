<script lang="ts">
    import type { Models } from '@type/type';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
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

    let activeTab = $state<'income' | 'expense' | 'transfer'>('expense');
</script>

<DashboardPageHeader title="New Transaction" />

<PageSection>
    <Tabs bind:value={activeTab}>
        <TabsList>
            <TabsTrigger value="income">Income</TabsTrigger>
            <TabsTrigger value="expense">Expense</TabsTrigger>
            <TabsTrigger value="transfer">Transfer</TabsTrigger>
        </TabsList>
    </Tabs>

    {#key activeTab}
        <div class="mt-4">
            <TransactionForm
                {accounts}
                {categories}
                onCancel={() => router.visit(TransactionController.index.url())}
                type={activeTab} />
        </div>
    {/key}
</PageSection>
