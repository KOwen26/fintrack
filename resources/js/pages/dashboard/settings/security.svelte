<script lang="ts" module>
    export { default as Layout } from '@components/layouts/dashboard-layout.svelte';
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import security from '@wayfinder/routes/security';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let { status }: { status: string | null } = $props();

    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const onsubmit = (e: SubmitEvent) => {
        e.preventDefault();
        form.put(security.update().url, {
            onSuccess: () => form.reset(),
        });
    };
</script>

<div class="space-y-6">
    <!-- Change Password Card -->
    <Card
        class="shadow-sm"
        contentClass="space-y-2"
        description="Ensure your account is using a strong, unique password."
        descriptionClass="text-base-content/70"
        title="Change Password">
        {#if status}
            <p class="text-sm text-success">{status}</p>
        {/if}

        <form class="space-y-4" {onsubmit}>
            <Field error={form.errors.current_password} title="Current Password">
                <PasswordInput name="current_password" bind:value={form.current_password} />
            </Field>

            <Field error={form.errors.password} title="New Password">
                <PasswordInput name="password" bind:value={form.password} />
            </Field>

            <Field error={form.errors.password_confirmation} title="Confirm Password">
                <PasswordInput
                    name="password_confirmation"
                    bind:value={form.password_confirmation} />
            </Field>

            <div class="flex flex-wrap items-center justify-between gap-2">
                {#if form.recentlySuccessful}
                    <span class="text-sm text-success">Password updated.</span>
                {:else}
                    <span></span>
                {/if}
                <SubmitButton submitting={form.processing}>Update Password</SubmitButton>
            </div>
        </form>
    </Card>

    <!-- Two-Factor Authentication Card (disabled) -->
    <Card
        class="opacity-60 shadow-sm"
        contentClass="space-y-2"
        description="Add an extra layer of security to your account using a TOTP authenticator app."
        descriptionClass="text-base-content/70"
        title="Two-Factor Authentication">
        {#snippet headerAction()}
            <Badge color="dark" size="sm">Coming Soon</Badge>
        {/snippet}
        <div class="flex flex-wrap gap-2">
            <Button size="sm" disabled variant="outline">Enable 2FA</Button>
        </div>
    </Card>

    <!-- Passkeys Card (disabled) -->
    <Card
        class="opacity-60 shadow-sm"
        contentClass="space-y-2"
        description="Sign in securely without a password using biometrics or a hardware key."
        descriptionClass="text-base-content/70"
        title="Passkeys">
        {#snippet headerAction()}
            <Badge color="dark" size="sm">Coming Soon</Badge>
        {/snippet}
        <div class="flex flex-wrap gap-2">
            <Button size="sm" disabled variant="outline">Manage Passkeys</Button>
        </div>
    </Card>
</div>
