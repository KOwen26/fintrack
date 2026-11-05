<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { page, useForm } from '@inertiajs/svelte';
    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import AccountSelect from '@components/module/account/account-select.svelte';
    import CategorySelect from '@components/module/category/category-select.svelte';
    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import CurrencyInput from '@components/ui/forms/currency-input.svelte';
    import DateInput from '@components/ui/forms/date-input.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import Select from '@components/ui/forms/select.svelte';
    import Textarea from '@components/ui/forms/textarea.svelte';

    let {
        row,
        batchId,
        batchAccountId,
        accounts,
        onDone,
    }: {
        row: App.Models.ImportRow;
        batchId: number;
        batchAccountId: number;
        accounts: App.Models.Account[];
        onDone: () => void;
    } = $props();

    let open = $state(true);

    function buildInitialData() {
        return {
            transaction_date: row.transaction_date?.slice(0, 10) ?? '',
            description: row.description ?? '',
            amount: row.amount ?? 0,
            transaction_type:
                row.flow === 'inflow' ? TransactionType.Income : TransactionType.Expense,
            category_id: row.category_id ?? '',
            transfer_destination_account_id: row.transfer_destination_account_id
                ? String(row.transfer_destination_account_id)
                : '',
        };
    }

    const form = useForm(buildInitialData());

    // Any modal dismissal (X button, overlay) hands control back to the parent.
    $effect(() => {
        if (!open) {
            onDone();
        }
    });

    const isTransfer = $derived(form.transaction_type === TransactionType.Transfer);

    const typeOptions = [
        { label: 'Income', value: TransactionType.Income },
        { label: 'Expense', value: TransactionType.Expense },
        { label: 'Transfer', value: TransactionType.Transfer },
    ];

    /* The category catalog follows the picked type — a category from the other
       catalog is cleared instead of silently mismatching at approve time. */
    const sharedCategories = $derived(
        (page.props?.static?.categories ?? {}) as Record<
            string,
            Array<{ id: string; options?: Array<{ id: string }> }>
        >
    );

    $effect(() => {
        if (isTransfer) return;

        const cashflow = form.transaction_type === TransactionType.Income ? 'income' : 'expense';
        const catalog = sharedCategories[`grouped_${cashflow}`] ?? [];

        // Catalog not loaded yet — never clear blind.
        if (catalog.length === 0 || !form.category_id) return;

        const categoryIds = catalog.flatMap(
            (group) => group.options?.map((option) => option.id) ?? []
        );

        if (!categoryIds.includes(String(form.category_id))) {
            form.category_id = '';
        }
    });
</script>

<DrawerModal title="Edit Row #{row.row_number}" bind:open>
    <Form
        id="import-row-form"
        class="space-y-5"
        action={ImportController.updateRow.url({ batch: batchId, row: row.id })}
        {form}
        method="patch">
        <Field error={form.errors.transaction_type} required title="Type">
            <Select options={typeOptions} required bind:value={form.transaction_type} />
        </Field>

        <Field error={form.errors.amount} required title="Amount">
            <CurrencyInput required bind:value={form.amount} />
        </Field>

        <Field error={form.errors.transaction_date} required title="Date">
            <DateInput required bind:value={form.transaction_date} />
        </Field>

        <Field error={form.errors.description} required title="Description">
            <Textarea
                placeholder="e.g. Pembayaran QR ke Khas Solo 609015160267"
                required
                bind:value={form.description} />
        </Field>

        {#if isTransfer}
            <Field
                error={form.errors.transfer_destination_account_id}
                required
                title="Transfer Destination">
                <AccountSelect
                    accounts={accounts.filter(
                        (account) => String(account.id) !== String(batchAccountId)
                    )}
                    required
                    bind:value={form.transfer_destination_account_id} />
            </Field>
        {:else}
            <Field error={form.errors.category_id} required title="Category">
                <CategorySelect required bind:value={form.category_id} />
            </Field>
        {/if}
    </Form>

    <div class="mt-4">
        <FormAction
            {form}
            formId="import-row-form"
            labelCancel="Close"
            labelSubmit="Save Row"
            onCancel={onDone} />
    </div>
</DrawerModal>
