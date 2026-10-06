<script lang="ts" module>
    export { default as Layout } from '@components/layouts/dashboard-layout.svelte';
</script>

<script lang="ts">
    import { router, useForm, usePage } from '@inertiajs/svelte';
    import authVerification from '@wayfinder/routes/auth/verification';
    import profile from '@wayfinder/routes/profile';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
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
    <Card
        class="shadow-sm"
        description="Update your name and email address."
        descriptionClass="text-base-content/70"
        title="Profile Information">
        <form class="mt-4 space-y-4" onsubmit={submitProfile}>
            <Field error={profileForm.errors.name} title="Name">
                <Input name="name" type="text" bind:value={profileForm.name} />
            </Field>

            <Field error={profileForm.errors.email} title="Email">
                <Input name="email" type="email" bind:value={profileForm.email} />
            </Field>

            <div class="flex flex-wrap items-center justify-between gap-2">
                {#if profileForm.recentlySuccessful}
                    <span class="text-sm text-success">Saved.</span>
                {:else}
                    <span></span>
                {/if}
                <SubmitButton submitting={profileForm.processing}>Save</SubmitButton>
            </div>
        </form>
    </Card>

    <!-- Email Verification Notice -->
    {#if mustVerifyEmail}
        <Card
            class="shadow-sm"
            contentClass="space-y-2"
            description="Your email address is unverified. Please check your inbox for a verification link."
            descriptionClass="text-base-content/70"
            title="Email Verification">
            {#if status === 'verification-link-sent'}
                <p class="text-sm text-success">
                    A new verification link has been sent to your email address.
                </p>
            {/if}
            <div class="flex flex-wrap gap-2">
                <Button size="sm" onclick={resendVerification} variant="outline">
                    Resend Verification Email
                </Button>
            </div>
        </Card>
    {/if}

    <!-- Delete Account Card -->
    <Card
        class="border-error shadow-sm"
        contentClass="space-y-2"
        description="Once your account is deleted, all of its resources and data will be permanently deleted."
        descriptionClass="text-base-content/70"
        title="Delete Account"
        titleClass="text-error">
        {#if !showDeleteConfirm}
            <div class="flex flex-wrap gap-2">
                <Button
                    size="sm"
                    color="error"
                    onclick={() => (showDeleteConfirm = true)}
                    variant="outline">
                    Delete Account
                </Button>
            </div>
        {:else}
            <form class="space-y-4" onsubmit={submitDelete}>
                <Field error={deleteForm.errors.password} title="Confirm your password to continue">
                    <PasswordInput name="password" bind:value={deleteForm.password} />
                </Field>

                <div class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        onclick={() => (showDeleteConfirm = false)}
                        type="button"
                        variant="ghost">
                        Cancel
                    </Button>
                    <SubmitButton size="sm" color="error" submitting={deleteForm.processing}>
                        Confirm Delete
                    </SubmitButton>
                </div>
            </form>
        {/if}
    </Card>
</div>
