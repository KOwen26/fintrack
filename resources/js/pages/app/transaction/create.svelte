<script lang="ts">
    import type { Data, Models } from '@type/type';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        transaction,
        accounts,
    }: {
        transaction: Data.TransactionFormData;
        accounts: Models.Account[];
    } = $props();

    setLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {transaction} />
