<script lang="ts">
    import type { Models } from '@type/type';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import MobileTransactionForm from '@components/module/transaction/mobile-transaction-form.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        categories,
        accounts,
    }: {
        categories: Models.Category[];
        accounts: Models.Account[];
    } = $props();

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
</HeaderContext>

<MobileTransactionForm {accounts} {categories} onCancel={cancel} />
