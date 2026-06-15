<script lang="ts">
    import type { InertiaForm } from '@inertiajs/svelte';

    import { HouseholdsController } from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import { householdInviteSchema } from '@schema/household.schema';

    import { DataComposer } from '@utilities/data-composer';

    import Card from '@components/ui/card.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';
    import FormGenerator from '@components/ui/forms/form-generator.svelte';

    let form: InertiaForm<any> = $state(null!);

    const formSchema = DataComposer.from(householdInviteSchema).toFormGenerator({ email: '' });
</script>

<Card title="Invite Partner">
    <FormGenerator
        id="invite-member"
        action={HouseholdsController.invite.url()}
        {formSchema}
        submitOptions={{ onSuccess: () => form?.reset?.() }}
        withoutSubmit
        bind:form />
    <div class="mt-4">
        <FormAction {form} formId="invite-member" labelSubmit="Send Invitation" withoutCancel />
    </div>
</Card>
