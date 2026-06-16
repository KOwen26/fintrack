<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import TransactionType from '@wayfinder/App/Enums/TransactionType';
    import TransactionsController from '@wayfinder/App/Http/Controllers/TransactionsController';

    import { transactionSchema } from '@schema/transaction.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        account: App.Models.Account;
        categories: App.Models.Category[];
        accounts?: App.Models.Account[]; // other accounts for transfer destination
        transaction?: App.Models.Transaction;
        onCancel?: () => void;
    }

    let { account, categories, accounts = [], transaction, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!transaction);

    // Flatten categories (parent + children) for select options
    const categoryOptions = $derived(() => {
        const opts: { value: string | number; label: string }[] = [
            { value: '', label: '— Uncategorized —' },
        ];

        for (const parent of categories) {
            if (parent.children && parent.children.length > 0) {
                for (const child of parent.children) {
                    opts.push({ value: child.id, label: `${parent.name} › ${child.name}` });
                }
            } else {
                opts.push({ value: parent.id, label: parent.name });
            }
        }

        return opts;
    });

    const accountOptions = $derived(() => accounts.map((a) => ({ value: a.id, label: a.name })));

    // Type options for create form — 'transfer' is a UI alias, never sent as-is to DB
    const typeOptions = [
        { value: 'income', label: 'Income' },
        { value: 'expense', label: 'Expense' },
        { value: 'transfer', label: 'Transfer' },
        { value: TransactionType.Fee, label: 'Fee' },
    ];

    const formSchema = $derived(() => {
        if (isEdit && transaction) {
            // Edit: amount, transaction_date, category_id, description only
            const { fields, data } = DataComposer.from(transactionSchema)
                .extendSchema({
                    category_id: {
                        label: 'Category',
                        form: () => ({
                            type: 'select',
                            name: 'category_id',
                            options: categoryOptions(),
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

        // Create: full form with type + conditional transfer fields
        const { fields, data } = DataComposer.from(transactionSchema)
            .extendSchema({
                type: {
                    label: 'Type',
                    form: () => ({
                        type: 'select',
                        name: 'type',
                        required: true,
                        options: typeOptions,
                    }),
                },
                category_id: {
                    label: 'Category',
                    form: () => ({
                        type: 'select',
                        name: 'category_id',
                        options: categoryOptions(),
                        show: (f: any) => f.type !== 'transfer',
                    }),
                },
                destination_account_id: {
                    label: 'Destination Account',
                    form: () => ({
                        type: 'select',
                        name: 'destination_account_id',
                        options: accountOptions(),
                        show: (f: any) => f.type === 'transfer',
                        required: false, // required rule in StoreTransactionRequest handles it server-side
                    }),
                },
                fee_amount: {
                    label: 'Transfer Fee (optional)',
                    form: () => ({
                        type: 'number',
                        name: 'fee_amount',
                        show: (f: any) => f.type === 'transfer',
                        inputProps: {
                            inputmode: 'decimal',
                            min: 0.01,
                            step: 0.01,
                            placeholder: '0.00',
                        },
                    }),
                },
            })
            .toFormGenerator({
                type: 'expense',
                amount: 0,
                transaction_date: new Date().toISOString().split('T')[0],
                category_id: '',
                destination_account_id: '',
                fee_amount: null,
                description: '',
            });

        return { fields, data };
    });

    const action = $derived(
        isEdit && transaction
            ? TransactionsController.update.url({
                  account: account.id,
                  transaction: transaction.id,
              })
            : TransactionsController.store.url({ account: account.id })
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Add Transaction');
</script>

<Card>
    <FormGenerator
        id="transaction-form"
        {action}
        formSchema={formSchema()}
        {method}
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="transaction-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())} />
</div>
