<script lang="ts">
    import type { Data, Models } from '@type/type';
    import type { App } from '@wayfinder/types';

    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import TransactionForm from '@components/module/transaction/transaction-form.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';

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

    const backUrl = TransactionController.index.url();
</script>

<HeaderContext>
    <div class="flex w-full items-center justify-between gap-3">
        <Button
            class="size-10 shrink-0 p-1 btn-sm md:hidden"
            aria-label="Back to transactions"
            href={backUrl}
            variant="ghost">
            <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
        </Button>

        <h1 class="grow text-center font-medium text-primary">Add New Transaction</h1>

        <div class="size-10"></div>
    </div>
</HeaderContext>

<TransactionForm {accounts} {categories} {initialType} {transaction} />
