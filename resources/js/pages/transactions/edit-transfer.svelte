<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import PageSection from '@components/layouts/page-section.svelte';
    import TransferForm from '@components/module/transaction/transfer-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';

    let {
        transfer,
        accounts,
    }: {
        transfer: App.Models.Transfer & { transactions: App.Models.Transaction[] };
        accounts: App.Models.Account[];
    } = $props();
</script>

<DashboardPageHeader title="Edit Transfer" />

<PageSection>
    <TransferForm
        {transfer}
        {accounts}
        onCancel={() => router.visit(TransactionController.index.url())} />
</PageSection>
