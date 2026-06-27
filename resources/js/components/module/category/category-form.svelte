<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';
    import type { App } from '@wayfinder/types';

    import CategoryController from '@wayfinder/App/Http/Controllers/CategoryController';

    import { categorySchema } from '@schema/category.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    interface Props {
        categories: App.Models.Category[];
        onSuccess?: () => void;
        onCancel?: () => void;
    }

    let { categories, onSuccess, onCancel }: Props = $props();

    let form: InertiaForm<any> = $state(null!);

    const parentOptions = $derived([
        { value: '', label: 'Top-level group' },
        ...categories.map((c) => ({ value: c.id, label: c.name })),
    ]);

    const formSchema = $derived(() => {
        const { fields, data } = DataComposer.from(categorySchema)
            .extendSchema({
                parent_id: {
                    label: 'Parent group',
                    form: () => ({ type: 'select', name: 'parent_id', options: parentOptions }),
                },
            })
            .toFormGenerator({
                name: '',
                'decorations.icon': 'ph:tag',
                'decorations.color': '#6366f1',
                type: 'output',
                order: '0.100',
                is_fixed_cost: false,
                parent_id: '',
            });

        return { fields, data };
    });
</script>

<Card class="mb-4">
    <FormGenerator
        id="add-category"
        action={CategoryController.store.url()}
        formSchema={formSchema()}
        submitOptions={{
            onSuccess: () => {
                form?.reset?.();
                onSuccess?.();
            },
        }}
        withoutSubmit
        bind:form />
    <div class="mt-4 flex gap-2">
        <FormAction class="flex-1" {form} formId="add-category" labelSubmit="Save" withoutCancel />
        {#if onCancel}
            <Button color="light" onclick={onCancel} variant="outline">Cancel</Button>
        {/if}
    </div>
</Card>
