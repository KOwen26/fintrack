<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateDetail from '@components/module/template/template-detail.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/cards/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    setLayoutProps({ title: template.name, backUrl: TransactionTemplateController.index.url() });

    let showDeleteConfirm = $state(false);

    function destroy(): void {
        router.delete(TransactionTemplateController.destroy.url({ template: template.id }));
    }
</script>

<MobilePageLayout variant="full">
    <div class="space-y-6">
        <Card title={template.name}>
            <TemplateDetail {template} />
        </Card>

        <div class="flex gap-3">
            <Button
                class="grow"
                color="primary"
                href={TransactionTemplateController.edit.url({ template: template.id })}
                variant="outline">
                Edit
            </Button>

            <Button color="error" onclick={() => (showDeleteConfirm = true)} variant="outline">
                <i class="iconify size-5 solar--trash-bin-minimalistic-bold-duotone"></i>
                Delete
            </Button>
        </div>
    </div>
</MobilePageLayout>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title="Delete Template"
    bind:open={showDeleteConfirm}>
    This will permanently delete the template. It cannot be undone.
</ConfirmationModal>
