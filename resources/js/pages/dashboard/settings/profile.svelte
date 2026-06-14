<script lang="ts" module>
    export { default as Layout } from '@components/layouts/dashboard-layout.svelte';
</script>

<script lang="ts">
    import { router, useForm, usePage } from '@inertiajs/svelte';
    import authVerification from '@wayfinder/routes/auth/verification';
    import profile from '@wayfinder/routes/profile';

    import Button from '@components/ui/button.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let { mustVerifyEmail, status }: { mustVerifyEmail: boolean; status: string | null } = $props();

    const page = usePage();
    const user = page.props?.auth?.user as { name: string; email: string } | undefined;

    const profileForm = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
    });

    const deleteForm = useForm({
        password: '',
    });

    let showDeleteConfirm = $state(false);

    const submitProfile = (e: SubmitEvent) => {
        e.preventDefault();
        profileForm.patch(profile.update().url);
    };

    const submitDelete = (e: SubmitEvent) => {
        e.preventDefault();
        deleteForm.delete(profile.destroy().url);
    };

    const resendVerification = () => {
        router.post(authVerification.send().url);
    };
</script>

<div class="space-y-6">
    <!-- Profile Info Card -->
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title">Profile Information</h2>
            <p class="text-base-content/70 text-sm">Update your name and email address.</p>

            <form class="mt-4 space-y-4" onsubmit={submitProfile}>
                <Field title="Name" error={profileForm.errors.name}>
                    <Input name="name" type="text" bind:value={profileForm.name} />
                </Field>

                <Field title="Email" error={profileForm.errors.email}>
                    <Input name="email" type="email" bind:value={profileForm.email} />
                </Field>

                <div class="card-actions mt-2 items-center justify-between">
                    {#if profileForm.recentlySuccessful}
                        <span class="text-success text-sm">Saved.</span>
                    {:else}
                        <span></span>
                    {/if}
                    <SubmitButton submitting={profileForm.processing}>Save</SubmitButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Verification Notice -->
    {#if mustVerifyEmail}
        <div class="card bg-base-100 shadow-sm">
            <div class="card-body">
                <h2 class="card-title">Email Verification</h2>
                <p class="text-base-content/70 text-sm">
                    Your email address is unverified. Please check your inbox for a verification
                    link.
                </p>
                {#if status === 'verification-link-sent'}
                    <p class="text-success text-sm">
                        A new verification link has been sent to your email address.
                    </p>
                {/if}
                <div class="card-actions mt-2">
                    <Button variant="outline" class="btn-sm" onclick={resendVerification}>
                        Resend Verification Email
                    </Button>
                </div>
            </div>
        </div>
    {/if}

    <!-- Delete Account Card -->
    <div class="card border-error bg-base-100 border shadow-sm">
        <div class="card-body">
            <h2 class="card-title text-error">Delete Account</h2>
            <p class="text-base-content/70 text-sm">
                Once your account is deleted, all of its resources and data will be permanently
                deleted.
            </p>

            {#if !showDeleteConfirm}
                <div class="card-actions mt-2">
                    <Button
                        color="error"
                        variant="outline"
                        class="btn-sm"
                        onclick={() => (showDeleteConfirm = true)}>
                        Delete Account
                    </Button>
                </div>
            {:else}
                <form class="mt-4 space-y-4" onsubmit={submitDelete}>
                    <Field
                        title="Confirm your password to continue"
                        error={deleteForm.errors.password}>
                        <PasswordInput name="password" bind:value={deleteForm.password} />
                    </Field>

                    <div class="card-actions mt-2 gap-2">
                        <Button
                            variant="ghost"
                            class="btn-sm"
                            type="button"
                            onclick={() => (showDeleteConfirm = false)}>
                            Cancel
                        </Button>
                        <SubmitButton
                            color="error"
                            class="btn-sm"
                            submitting={deleteForm.processing}>
                            Confirm Delete
                        </SubmitButton>
                    </div>
                </form>
            {/if}
        </div>
    </div>
</div>
