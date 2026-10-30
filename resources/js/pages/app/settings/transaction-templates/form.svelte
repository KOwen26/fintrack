<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps, useForm } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateForm from '@components/module/template/template-form.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import FormAction from '@components/ui/forms/form-action.svelte';

    let {
        template = null,
        accounts,
        tags = [],
    }: {
        template?: App.Models.TransactionTemplate | null;
        accounts: App.Models.Account[];
        tags?: string[];
    } = $props();

    setLayoutProps({
        title: template ? 'Edit Template' : 'Add Template',
        backUrl: template
            ? TransactionTemplateController.show.url({ template: template.id })
            : TransactionTemplateController.index.url(),
    });

    const isEdit = $derived(template !== null);

    /* Created inside TemplateForm, bound up — the `bind:form` pattern. */
    let form: ReturnType<typeof useForm> | undefined = $state();

    function cancel(): void {
        router.visit(
            isEdit && template
                ? TransactionTemplateController.show.url({ template: template.id })
                : TransactionTemplateController.index.url()
        );
    }

    const submitLabel = $derived(isEdit ? 'Save Changes' : 'Create Template');

    const formActionProps = $derived(
        form && {
            cancelClass: 'flex-auto',
            submitClass: 'flex-3/5',
            form,
            formId: 'transaction-template-form',
            labelCancel: 'Cancel',
            labelSubmit: submitLabel,
            onCancel: cancel,
        }
    );
</script>

<MobilePageLayout variant="full">
    <TemplateForm {accounts} {tags} {template} bind:form />
</MobilePageLayout>

{#if formActionProps}
    <BottomActionBar>
        <FormAction class="w-full" {...formActionProps} />
    </BottomActionBar>
{/if}
