<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
    import TransactionPresetsController from '@wayfinder/App/Http/Controllers/TransactionPresetsController';

    import { transactionPresetSchema } from '@schema/transaction-preset.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        preset?: App.Models.TransactionPreset;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let { accounts, categories, preset, onSuccess, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!preset);

    const accountOptions = $derived(accounts.map((a) => ({ value: a.id, label: a.name })));

    const categoryOptions = $derived([
        { value: '', label: '— None —' },
        ...categories.flatMap((parent) => [
            { value: parent.id, label: parent.name },
            ...(parent.children ?? []).map((child: App.Models.Category) => ({
                value: child.id,
                label: `  ${child.name}`,
            })),
        ]),
    ]);

    const formSchema = $derived(() => {
        const composer = DataComposer.from(transactionPresetSchema).extendSchema({
            default_category_id: {
                label: 'Default Category',
                form: () => ({
                    type: 'select',
                    name: 'default_category_id',
                    options: categoryOptions,
                }),
            },
            default_source_account_id: {
                label: 'Source Account',
                form: () => ({
                    type: 'select',
                    name: 'default_source_account_id',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    options: accountOptions,
                }),
            },
            default_destination_account_id: {
                label: 'Destination Account',
                form: () => ({
                    type: 'select',
                    name: 'default_destination_account_id',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    options: accountOptions,
                }),
            },
            default_transfer_fee: {
                label: 'Transfer Fee',
                form: () => ({
                    type: 'number',
                    name: 'default_transfer_fee',
                    show: (f: any) => f.type === TransactionPresetType.Transfer,
                    inputProps: { inputmode: 'decimal', min: 0, step: 0.01, placeholder: '0' },
                }),
            },
        });

        if (isEdit && preset) {
            return composer.toFormGenerator({
                name: preset.name,
                type: preset.type,
                default_amount:
                    preset.default_amount != null ? Number(preset.default_amount) : null,
                default_description: preset.default_description ?? '',
                default_category_id: preset.default_category_id ?? '',
                default_source_account_id: preset.default_source_account_id ?? '',
                default_destination_account_id: preset.default_destination_account_id ?? '',
                default_transfer_fee:
                    preset.default_transfer_fee != null
                        ? Number(preset.default_transfer_fee)
                        : null,
            });
        }

        return composer.toFormGenerator({
            name: '',
            type: TransactionPresetType.Expense,
            default_amount: null,
            default_description: '',
            default_category_id: '',
            default_source_account_id: '',
            default_destination_account_id: '',
            default_transfer_fee: null,
        });
    });

    const action = $derived(
        isEdit && preset
            ? TransactionPresetsController.update.url({ preset: preset.id })
            : TransactionPresetsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Template');

    const submitOptions = $derived(
        isEdit
            ? undefined
            : {
                  onSuccess: () => {
                      form?.reset?.();
                      onSuccess?.();
                  },
              }
    );
</script>

<Card>
    <FormGenerator
        id="preset-form"
        {action}
        formSchema={formSchema()}
        {method}
        {submitOptions}
        withoutSubmit
        bind:form />
</Card>

<div class="mt-4">
    <FormAction
        {form}
        formId="preset-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit} />
</div>
