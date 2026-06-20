<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { expenseSchema, incomeSchema, transferSchema } from '@schema/transaction.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        type?: 'income' | 'expense' | 'transfer';
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts?: App.Models.Account[];
        transaction?: App.Models.Transaction;
        onCancel?: () => void;
    }

    let {
        type = 'expense',
        account,
        categories,
        accounts = [],
        transaction,
        onCancel,
    }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!transaction);

    const today = new Date().toISOString().split('T')[0];

    const formSchema = $derived(() => {
        if (isEdit && transaction) {
            const isTransferType =
                transaction.type === 'transfer_out' ||
                transaction.type === 'transfer_in' ||
                transaction.type === 'fee';

            if (isTransferType) {
                return DataComposer.from(
                    DataComposer.toSchema(transferSchema, {
                        only: ['amount', 'transaction_date', 'description'],
                    })
                ).toFormGenerator({
                    amount: Number(transaction.amount),
                    transaction_date: transaction.transaction_date as string,
                    description: transaction.description ?? '',
                });
            }

            const baseSchema = transaction.type === 'income' ? incomeSchema : expenseSchema;

            const { fields, data } = DataComposer.from(baseSchema)
                .extendSchema({
                    category_id: {
                        label: 'Category',
                        form: () => ({
                            type: 'category-select',
                            name: 'category_id',
                            categories,
                        }),
                    },
                })
                .toFormGenerator({
                    amount: Number(transaction.amount),
                    transaction_date: transaction.transaction_date as string,
                    category_id: transaction.category_id ?? '',
                    description: transaction.description ?? '',
                });

            return { fields, data };
        }

        if (type === 'transfer') {
            const { fields, data } = DataComposer.from(transferSchema)
                .extendSchema({
                    destination_account_id: {
                        label: 'Destination Account',
                        form: () => ({
                            type: 'account-select',
                            name: 'destination_account_id',
                            required: true,
                            accounts,
                            placeholder: 'Select destination',
                        }),
                    },
                })
                .toFormGenerator({
                    amount: 0,
                    transaction_date: today,
                    fee_amount: null,
                    description: '',
                });

            data.type = 'transfer';

            return { fields, data };
        }

        const baseSchema = type === 'income' ? incomeSchema : expenseSchema;

        const { fields, data } = DataComposer.from(baseSchema)
            .extendSchema({
                category_id: {
                    label: 'Category',
                    form: () => ({
                        type: 'category-select',
                        name: 'category_id',
                        categories,
                    }),
                },
            })
            .toFormGenerator({
                amount: 0,
                transaction_date: today,
                category_id: '',
                description: '',
            });

        data.type = type === 'income' ? 'income' : 'expense';

        return { fields, data };
    });

    const action = $derived(
        isEdit && transaction
            ? TransactionController.update.url(transaction.id)
            : TransactionController.store.url()
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);

    const submitLabel = $derived(
        isEdit
            ? 'Save Changes'
            : type === 'income'
              ? 'Add Income'
              : type === 'transfer'
                ? 'Add Transfer'
                : 'Add Expense'
    );
</script>

{#key isEdit ? 'edit' : type}
    <Card>
        <FormGenerator
            id="transaction-form"
            {action}
            formSchema={formSchema()}
            {method}
            withoutSubmit
            bind:form />
    </Card>
{/key}

<div class="mt-4">
    <FormAction
        {form}
        formId="transaction-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())} />
</div>
