<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import HouseholdInvitationsController from '@wayfinder/App/Http/Controllers/HouseholdInvitationsController';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Form from '@components/ui/forms/form.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let {
        invitation,
    }: {
        invitation: {
            token: string;
            household_name: string;
            invited_by: string;
            expires_at: string;
        };
    } = $props();

    const acceptForm = useForm({});
    const declineForm = useForm({});
    const expiresAt = $derived(new Date(invitation.expires_at).toLocaleDateString('id-ID'));
</script>

<div class="flex min-h-screen items-center justify-center p-6">
    <Card wrapperClass="w-full max-w-sm text-center shadow-lg">
        <i class="iconify mx-auto mb-4 size-12 ph--envelope-open-bold text-primary"></i>
        <h1 class="mb-1 text-lg font-bold">You're invited!</h1>
        <p class="mb-2 text-sm text-base-content/60">
            <strong>{invitation.invited_by}</strong> invited you to join
        </p>
        <p class="mb-1 text-xl font-bold">{invitation.household_name}</p>
        <p class="mb-6 text-xs text-base-content/40">Expires {expiresAt}</p>

        <div class="space-y-3">
            <Form
                action={HouseholdInvitationsController.accept.url({ token: invitation.token })}
                form={acceptForm}>
                <SubmitButton class="w-full" color="primary" submitting={acceptForm.processing}>
                    Accept Invitation
                </SubmitButton>
            </Form>
            <Form
                action={HouseholdInvitationsController.decline.url({ token: invitation.token })}
                form={declineForm}>
                <Button
                    class="w-full btn-sm"
                    color="light"
                    disabled={declineForm.processing}
                    variant="ghost">
                    Decline
                </Button>
            </Form>
        </div>
    </Card>
</div>
