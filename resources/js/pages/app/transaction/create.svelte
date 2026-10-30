<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import { pageLayoutProps } from '@lib/navigation-helper.svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';

    let {
        transaction,
        accounts,
        templates = [],
        tags = [],
    }: {
        transaction: Data.TransactionFormData;
        accounts: Models.Account[];
        templates?: App.Models.TransactionTemplate[];
        tags?: string[];
    } = $props();

    pageLayoutProps({ title: 'Add New Transaction', backUrl: TransactionController.index.url() });
</script>

<TransactionForm {accounts} {tags} {templates} {transaction} />
