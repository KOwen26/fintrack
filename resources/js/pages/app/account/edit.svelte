<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router, setLayoutProps } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';

    import AccountForm from '@components/module/account/account-form.svelte';
    import HeaderContext from '@components/navigation/header-context.svelte';
    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu';
    import Button from '@components/ui/button.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { account, providers }: { account: App.Models.Account; providers: App.Models.Provider[] } =
        $props();

    setLayoutProps({
        title: 'Edit Account',
        backUrl: AccountController.show.url({ account: account.id }),
    });

    let showArchiveConfirm = $state(false);
    let showDeleteConfirm = $state(false);

    function archive() {
        router.post(AccountController.archive.url({ account: account.id }));
    }
    function destroy() {
        router.delete(AccountController.destroy.url({ account: account.id }));
    }
</script>

<HeaderContext>
    {#snippet actions()}
        <DropdownMenu.Root>
            <DropdownMenu.Trigger>
                {#snippet child({ props })}
                    <Button {...props} color="primary" size="icon" variant="soft">
                        <i class="iconify size-5 solar--menu-dots-vertical-bold-duotone"></i>
                    </Button>
                {/snippet}
            </DropdownMenu.Trigger>

            <DropdownMenu.Content class="w-44 rounded-lg" align="end">
                <DropdownMenu.Item onclick={() => (showArchiveConfirm = true)}>
                    <i class="iconify size-5 solar--archive-bold-duotone"></i>
                    Archive Account
                </DropdownMenu.Item>

                <DropdownMenu.Item onclick={() => (showDeleteConfirm = true)} variant="destructive">
                    <i class="iconify size-5 solar--trash-bin-2-bold-duotone"></i>
                    Delete Account
                </DropdownMenu.Item>
            </DropdownMenu.Content>
        </DropdownMenu.Root>
    {/snippet}
</HeaderContext>

<AccountForm
    {account}
    onCancel={() => router.visit(AccountController.show.url({ account: account.id }))}
    {providers} />

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
