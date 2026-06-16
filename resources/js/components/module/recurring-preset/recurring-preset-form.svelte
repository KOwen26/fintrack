<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';
    import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';
    import RecurringPresetsController from '@wayfinder/App/Http/Controllers/RecurringPresetsController';

    import { recurringPresetSchema } from '@schema/recurring-preset.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        accounts: App.Models.Account[];
        categories: App.Models.Category[];
        preset?: App.Models.TransactionRecurringPreset;
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
        const composer = DataComposer.from(recurringPresetSchema).extendSchema({
            account_id: {
                label: 'Account',
                form: () => ({
                    type: 'select',
                    name: 'account_id',
                    required: true,
                    options: accountOptions,
                }),
            },
            category_id: {
                label: 'Category',
                form: () => ({
                    type: 'select',
                    name: 'category_id',
                    options: categoryOptions,
                }),
            },
        });

        if (isEdit && preset) {
            return composer.toFormGenerator({
                account_id: preset.account_id,
                category_id: preset.category_id ?? '',
                name: preset.name,
                type: preset.type,
                frequency: preset.frequency,
                amount: Number(preset.amount),
                description: preset.description ?? '',
                next_run_date: preset.next_run_date,
                recurrence_end_date: preset.recurrence_end_date ?? '',
            });
        }

        return composer.toFormGenerator({
            account_id: accounts[0]?.id ?? '',
            category_id: '',
            name: '',
            type: TransactionPresetType.Expense,
            frequency: RecurringFrequency.Monthly,
            amount: 0,
            description: '',
            next_run_date: '',
            recurrence_end_date: '',
        });
    });

    const action = $derived(
        isEdit && preset
            ? RecurringPresetsController.update.url({ preset: preset.id })
            : RecurringPresetsController.store.url()
    );

    const method = $derived(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Rule');

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
        id="recurring-preset-form"
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
        formId="recurring-preset-form"
        labelCancel="Cancel"
        labelSubmit={submitLabel}
        onCancel={onCancel ?? (() => window.history.back())}
        withoutCancel={!onCancel && !isEdit} />
</div>
