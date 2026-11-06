<script lang="ts">
    import type { ImportRowData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { useForm } from '@inertiajs/svelte';
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
        batch,
        accounts,
        onClose,
    }: {
        row: ImportRowData;
        batch: App.Models.ImportBatch;
        accounts: App.Models.Account[];
        onClose: () => void;
    } = $props();

    let open = $state(true);

    const form = useForm(() => ({
        transaction_date: row.transaction_date?.slice(0, 10) ?? '',
        description: row.description ?? '',
        amount: row.amount ?? 0,
        transaction_type: row.transaction_type ?? TransactionType.Expense,
        category_id: row.category_id ?? '',
        transfer_destination_account_id: row.transfer_destination_account_id ?? '',
    }));

    // Any modal dismissal (X button, overlay) hands control back to the parent.
    $effect(() => {
        if (!open) {
            onClose();
        }
    });

    const isTransfer = $derived(form.transaction_type === TransactionType.Transfer);

    const typeOptions = [
        { label: 'Income', value: TransactionType.Income },
        { label: 'Expense', value: TransactionType.Expense },
        { label: 'Transfer', value: TransactionType.Transfer },
    ];
</script>

<DrawerModal title="Edit Row #{row.row_number}" bind:open>
    <div class="px-5">
        <Form
            id="import-row-form"
            class="space-y-5"
            action={ImportController.updateRow.url({ batch: batch.id, row: row.id })}
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

            {#if isTransfer}
                <Field
                    error={form.errors.transfer_destination_account_id}
                    required
                    title="Transfer Destination">
                    <AccountSelect
                        accounts={accounts.filter(
                            (acct) => String(acct.id) !== String(batch.account_id)
                        )}
                        required
                        bind:value={form.transfer_destination_account_id} />
                </Field>
            {:else}
                <Field error={form.errors.category_id} required title="Category">
                    <CategorySelect
                        cashflow={form.transaction_type === TransactionType.Income
                            ? 'inflow'
                            : 'outflow'}
                        required
                        bind:value={form.category_id} />
                </Field>
            {/if}

            <Field error={form.errors.description} required title="Description">
                <Textarea required bind:value={form.description} />
            </Field>
        </Form>
    </div>

    {#snippet footer()}
        <div class="p-5">
            <FormAction
                {form}
                formId="import-row-form"
                labelCancel="Close"
                labelSubmit="Save Row"
                onCancel={onClose} />
        </div>
    {/snippet}
</DrawerModal>
