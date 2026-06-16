<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import BudgetsController from '@wayfinder/App/Http/Controllers/BudgetsController';

    import { budgetSchema } from '@schema/budget.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        account: App.Models.Account;
        categories: App.Models.Category[];
        budget?: App.Models.Budget;
        defaultYear?: number;
        defaultMonth?: number;
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let {
        account,
        categories,
        budget,
        defaultYear = new Date().getFullYear(),
        defaultMonth = new Date().getMonth() + 1,
        onSuccess,
        onCancel,
    }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const isEdit = $derived(!!budget);

    // Flatten category hierarchy for select options
    const categoryOptions = $derived(() => {
        const opts: { value: string | number; label: string }[] = [];

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

    // Generate month names
    const monthNames = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    function generateYearOptions(): { value: number; label: string }[] {
        const currentYear = new Date().getFullYear();

        return [currentYear - 1, currentYear, currentYear + 1].map((y) => ({
            value: y,
            label: String(y),
        }));
    }

    function generateMonthOptions(): { value: number; label: string }[] {
        return monthNames.map((name, idx) => ({ value: idx + 1, label: name }));
    }

    const formSchema = $derived(() => {
        if (isEdit && budget) {
            // Edit: limit_amount only
            return DataComposer.from(budgetSchema).toFormGenerator({
                limit_amount: Number(budget.limit_amount),
            });
        }

        // Create: full form
        return DataComposer.from(budgetSchema)
            .extendSchema({
                category_id: {
                    label: 'Category',
                    form: () => ({
                        type: 'select',
                        name: 'category_id',
                        required: true,
                        options: categoryOptions(),
                    }),
                },
                year: {
                    label: 'Year',
                    form: () => ({
                        type: 'select',
                        name: 'year',
                        required: true,
                        options: generateYearOptions(),
                    }),
                },
                month: {
                    label: 'Month',
                    form: () => ({
                        type: 'select',
                        name: 'month',
                        required: true,
                        options: generateMonthOptions(),
                    }),
                },
            })
            .toFormGenerator({
                category_id: '',
                limit_amount: 0,
                year: defaultYear,
                month: defaultMonth,
            });
    });

    const action = $derived(
        isEdit && budget
            ? BudgetsController.update.url({ account: account.id, budget: budget.id })
            : BudgetsController.store.url({ account: account.id })
    );

    const method = $derived<'put' | undefined>(isEdit ? 'put' : undefined);
    const submitLabel = $derived(isEdit ? 'Update Limit' : 'Set Budget');
</script>

<Card wrapperClass="mb-4">
    <FormGenerator
        id="budget-form"
        {action}
        formSchema={formSchema()}
        {method}
        submitOptions={{
            onSuccess: () => {
                form?.reset?.();
                onSuccess?.();
            },
        }}
        withoutSubmit
        bind:form />
    <div class="mt-4 flex gap-2">
        <FormAction
            class="flex-1"
            {form}
            formId="budget-form"
            labelSubmit={submitLabel}
            withoutCancel={!onCancel} />
        {#if onCancel}
            <Button color="light" onclick={onCancel} variant="outline">Cancel</Button>
        {/if}
    </div>
</Card>
