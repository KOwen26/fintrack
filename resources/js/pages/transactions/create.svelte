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
    import HeaderContext from '@components/navigation/header-context.svelte';
    import TabsList from '@components/ui/atoms/tabs/tabs-list.svelte';
    import TabsTrigger from '@components/ui/atoms/tabs/tabs-trigger.svelte';
    import Tabs from '@components/ui/atoms/tabs/tabs.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        categories,
        accounts,
    }: {
        categories: Models.Category[];
        accounts: Models.Account[];
    } = $props();

    let activeTab = $state<App.Enums.TransactionType>(TransactionType.Expense);

    const backUrl = TransactionController.index.url();
    const cancel = () => router.visit(backUrl);
</script>

<HeaderContext>
    <Button
        class="size-10 shrink-0 p-1 btn-sm md:hidden"
        aria-label="Back to transactions"
        color="secondary"
        href={backUrl}
        variant="ghost">
        <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
    </Button>

    <Tabs class="min-w-0 flex-1 flex-row md:flex-none" bind:value={activeTab}>
        <TabsList class="w-full md:w-fit">
            <TabsTrigger value={TransactionType.Income}>Income</TabsTrigger>
            <TabsTrigger value={TransactionType.Expense}>Expense</TabsTrigger>
            <TabsTrigger value={TransactionType.Transfer}>Transfer</TabsTrigger>
        </TabsList>
    </Tabs>
</HeaderContext>

<DashboardPageHeader title="New Transaction" />

<PageSection>
    {#key activeTab}
        {#if activeTab === TransactionType.Transfer}
            <TransferForm {accounts} onCancel={cancel} />
        {:else}
            <TransactionForm {accounts} {categories} onCancel={cancel} type={activeTab} />
        {/if}
    {/key}
</PageSection>
