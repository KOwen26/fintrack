<script lang="ts">
    import type { Page } from '@type/inertia';

    import { router, setLayoutProps, useForm } from '@inertiajs/svelte';
    import authVerification from '@wayfinder/routes/auth/verification';
    import profile from '@wayfinder/routes/profile';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let {
        mustVerifyEmail,
        canDeleteAccount = false,
        auth,
    }: {
        mustVerifyEmail: boolean;
        canDeleteAccount: boolean;
        auth: Page['auth'];
    } = $props();

    setLayoutProps({ title: 'Profile', backUrl: settings.index.url() });

    const profileForm = useForm({
        name: auth?.user?.name ?? '',
        email: auth?.user?.email ?? '',
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

<MobilePageLayout variant="full">
    <div class="space-y-6">
        <!-- Profile Info Card -->
        <Card
            descriptionClass="text-foreground/70"
            description="Update your name and email address."
            title="Profile Information">
            <form class="space-y-4" onsubmit={submitProfile}>
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
                    <SubmitButton class="min-w-25" submitting={profileForm.processing}
                        >Save</SubmitButton>
                </div>
            </form>
        </Card>

        <!-- Email Verification Notice -->
        {#if mustVerifyEmail}
            <Card
                descriptionClass="text-foreground/70"
                description="Your email address is unverified. Please check your inbox for a verification link."
                title="Email Verification">
                <div class="space-y-4">
                    {#if status === 'verification-link-sent'}
                        <p class="text-sm text-success">
                            A new verification link has been sent to your email address.
                        </p>
                    {/if}

                    <div class="text-right">
                        <Button onclick={resendVerification} variant="outline">
                            Resend Verification Email
                        </Button>
                    </div>
                </div>
            </Card>
        {/if}

        <!-- Delete Account Card -->
        {#if canDeleteAccount}
            <Card
                class="border-error"
                descriptionClass="text-foreground/70"
                titleClass="text-error"
                description="Once your account is deleted, all of its resources and data will be permanently deleted."
                title="Delete Account">
                {#if !showDeleteConfirm}
                    <div class="text-right">
                        <Button
                            class="min-w-25"
                            color="error"
                            onclick={() => (showDeleteConfirm = true)}
                            variant="outline">
                            Delete Account
                        </Button>
                    </div>
                {:else}
                    <form class="space-y-4" onsubmit={submitDelete}>
                        <Field
                            error={deleteForm.errors.password}
                            title="Confirm your password to continue">
                            <PasswordInput name="password" bind:value={deleteForm.password} />
                        </Field>

                        <div class="flex flex-wrap gap-2">
                            <Button
                                onclick={() => (showDeleteConfirm = false)}
                                type="button"
                                variant="ghost">
                                Cancel
                            </Button>

                            <SubmitButton color="error" submitting={deleteForm.processing}>
                                Confirm Delete
                            </SubmitButton>
                        </div>
                    </form>
                {/if}
            </Card>
        {/if}
    </div>
</MobilePageLayout>
