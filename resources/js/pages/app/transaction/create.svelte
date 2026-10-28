<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        transaction,
        accounts,
        templates = [],
    }: {
        transaction: Data.TransactionFormData;
        accounts: Models.Account[];
        templates?: App.Models.TransactionTemplate[];
    } = $props();

    setLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {templates} {transaction} />
