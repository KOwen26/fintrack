<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';

    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import { householdSchema } from '@schema/household.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    let form: InertiaForm<any> = $state(null!);

    const formSchema = DataComposer.from(householdSchema).toFormGenerator({ name: '' });
</script>

<Card title="Create Household">
    <FormGenerator
        id="create-household"
        action={HouseholdsController.store.url()}
        {formSchema}
        withoutSubmit
        bind:form />
    <div class="mt-4">
        <FormAction {form} formId="create-household" labelSubmit="Create Household" withoutCancel />
    </div>
</Card>
