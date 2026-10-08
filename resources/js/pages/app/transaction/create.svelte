<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        initialType = 'expense',
        transaction,
        categories,
        accounts,
    }: {
        initialType: App.Enums.TransactionType;
        transaction: Data.TransactionFormData;
        categories: Models.Category[];
        accounts: Models.Account[];
    } = $props();

    setLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {categories} {initialType} {transaction} />
