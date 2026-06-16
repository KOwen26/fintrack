<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionsController from '@wayfinder/App/Http/Controllers/TransactionsController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        account,
        categories,
        accounts,
    }: {
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts: App.Models.Account[];
    } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={TransactionsController.index.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">New Transaction</h1>
    </div>

    <TransactionForm
        {account}
        {accounts}
        {categories}
        onCancel={() => router.visit(TransactionsController.index.url({ account: account.id }))} />
</div>
