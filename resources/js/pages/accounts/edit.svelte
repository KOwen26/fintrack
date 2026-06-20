<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { account, providers }: { account: App.Models.Account; providers: App.Models.Provider[] } =
        $props();

    let showArchiveConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    function archive() {
        router.post(AccountController.archive.url({ account: account.id }));
    }
    function destroy() {
        router.delete(AccountController.destroy.url({ account: account.id }));
    }
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountController.show.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <h1 class="text-xl font-bold">Edit Account</h1>
    </div>

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
            <i class="iconify size-4 ph--archive-bold"></i>
            Archive Account
        </Button>
        <Button
            class="w-full"
            color="error"
            onclick={() => (showDeleteConfirm = true)}
            variant="outline">
            <i class="iconify size-4 ph--trash-bold"></i>
            Delete Account
        </Button>
    </div>
</div>

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
