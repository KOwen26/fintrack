<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import HouseholdsController from '@wayfinder/App/Http/Controllers/HouseholdsController';

    import HouseholdForm from '@components/module/household/household-form.svelte';
    import HouseholdInviteForm from '@components/module/household/household-invite-form.svelte';
    import HouseholdMemberRoleBadge from '@components/module/household/household-member-role-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let { household }: { household: App.Data.HouseholdData | null } = $props();
    let removingMemberId = $state<number | null>(null);
    let showRemoveConfirm = $state(false);

    function confirmRemove(id: number) {
        removingMemberId = id;
        showRemoveConfirm = true;
    }

    function cancelRemove() {
        removingMemberId = null;
        showRemoveConfirm = false;
    }

    function removeMember() {
        if (!removingMemberId) return;
        router.delete(HouseholdsController.removeMember.url({ member: removingMemberId }), {
            onFinish: () => {
                removingMemberId = null;
                showRemoveConfirm = false;
            },
        });
    }
</script>

<div class="p-4">
    <h1 class="mb-4 text-xl font-bold">Household</h1>

    {#if !household}
        <HouseholdForm />
    {:else}
        <Card wrapperClass="mb-4" title={household.name}>
            <div class="divide-y divide-base-200">
                {#each household.members as member (member.id)}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium">{member.name}</span>
                            <HouseholdMemberRoleBadge role={member.role} />
                        </div>
                        {#if member.role !== 'owner'}
                            <Button
                                class="btn-xs"
                                color="error"
                                onclick={() => confirmRemove(member.id)}
                                variant="ghost">
                                Remove
                            </Button>
                        {/if}
                    </div>
                {/each}
            </div>
        </Card>

        <HouseholdInviteForm />
    {/if}
</div>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Remove"
    onCancel={cancelRemove}
    onConfirm={removeMember}
    title="Remove Member"
    bind:open={showRemoveConfirm}>
    This member will lose access to all joint accounts in this household.
</ConfirmationModal>
