<script lang="ts" module>
    export { default as Layout } from '@components/layouts/dashboard-layout.svelte';
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import security from '@wayfinder/routes/security';

    import Button from '@components/ui/button.svelte';
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
    <div class="card bg-base-100 shadow-sm">
        <div class="card-body">
            <h2 class="card-title">Change Password</h2>
            <p class="text-base-content/70 text-sm">
                Ensure your account is using a strong, unique password.
            </p>

            {#if status}
                <p class="text-success text-sm">{status}</p>
            {/if}

            <form class="mt-4 space-y-4" {onsubmit}>
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

                <div class="card-actions mt-2 items-center justify-between">
                    {#if form.recentlySuccessful}
                        <span class="text-success text-sm">Password updated.</span>
                    {:else}
                        <span></span>
                    {/if}
                    <SubmitButton submitting={form.processing}>Update Password</SubmitButton>
                </div>
            </form>
        </div>
    </div>

    <!-- Two-Factor Authentication Card (disabled) -->
    <div class="card bg-base-100 opacity-60 shadow-sm">
        <div class="card-body">
            <div class="flex items-center gap-3">
                <h2 class="card-title">Two-Factor Authentication</h2>
                <span class="badge badge-neutral badge-sm">Coming Soon</span>
            </div>
            <p class="text-base-content/70 text-sm">
                Add an extra layer of security to your account using a TOTP authenticator app.
            </p>
            <div class="card-actions mt-2">
                <Button class="btn-sm" disabled variant="outline">Enable 2FA</Button>
            </div>
        </div>
    </div>

    <!-- Passkeys Card (disabled) -->
    <div class="card bg-base-100 opacity-60 shadow-sm">
        <div class="card-body">
            <div class="flex items-center gap-3">
                <h2 class="card-title">Passkeys</h2>
                <span class="badge badge-neutral badge-sm">Coming Soon</span>
            </div>
            <p class="text-base-content/70 text-sm">
                Sign in securely without a password using biometrics or a hardware key.
            </p>
            <div class="card-actions mt-2">
                <Button class="btn-sm" disabled variant="outline">Manage Passkeys</Button>
            </div>
        </div>
    </div>
</div>
