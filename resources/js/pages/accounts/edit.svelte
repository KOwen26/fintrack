<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import PageSection from '@components/layouts/page-section.svelte';
    import AccountForm from '@components/module/account/account-form.svelte';
    import DashboardPageHeader from '@components/navigation/dashboard-page-header.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { account, providers }: { account: App.Models.Account; providers: App.Models.Provider[] } =
        $props();

    setLayoutProps({ backUrl: AccountController.show.url({ account: account.id }) });

    let showArchiveConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    function archive() {
        router.post(AccountController.archive.url({ account: account.id }));
    }
    function destroy() {
        router.delete(AccountController.destroy.url({ account: account.id }));
    }
</script>

<DashboardPageHeader title="Edit Account" />

<PageSection>
    <AccountForm
        {account}
        onCancel={() => router.visit(AccountController.show.url({ account: account.id }))}
        {providers} />

    <div class="mt-4 space-y-3">
        <Button
            class="w-full"
            color="warning"
            onclick={() => (showArchiveConfirm = true)}
            variant="outline">
            <i class="iconify size-4 solar--archive-bold-duotone"></i>
            Archive Account
        </Button>
        <Button
            class="w-full"
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="outline">
            <i class="iconify size-4 solar--trash-bin-2-bold-duotone"></i>
            Delete Account
        </Button>
    </div>
</PageSection>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'warning' }}
    confirmText="Archive"
    onConfirm={archive}
    title="Archive Account"
    bind:open={showArchiveConfirm}>
    This account will be hidden from active views. You can restore it later.
</ConfirmationModal>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onConfirm={destroy}
    title="Delete Account"
    bind:open={showDeleteConfirm}>
    This will permanently delete the account and cannot be undone.
</ConfirmationModal>
