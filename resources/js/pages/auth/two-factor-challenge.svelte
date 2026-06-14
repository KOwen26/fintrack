<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import authTwoFactor from '@wayfinder/routes/auth/two-factor';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Button from '@components/ui/button.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let useRecovery = $state(false);

    const form = useForm({
        code: '',
        recovery_code: '',
    });

    const onsubmit = (e: SubmitEvent) => {
        e.preventDefault();
        form.post(authTwoFactor.login().url, {
            onFinish: () => form.reset('code', 'recovery_code'),
        });
    };
</script>

<AuthLayout>
    <h1>Two-Factor Authentication</h1>
    <p class="text-base-content/70 text-sm">
        {#if useRecovery}
            Enter one of your emergency recovery codes to confirm your identity.
        {:else}
            Enter the 6-digit code from your authenticator app.
        {/if}
    </p>

    <form class="mt-6 space-y-4" {onsubmit}>
        {#if !useRecovery}
            <Field title="Authentication Code" error={form.errors.code}>
                <Input
                    name="code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="123456"
                    bind:value={form.code} />
            </Field>
        {:else}
            <Field title="Recovery Code" error={form.errors.recovery_code}>
                <Input
                    name="recovery_code"
                    type="text"
                    autocomplete="one-time-code"
                    placeholder="xxxx-xxxx"
                    bind:value={form.recovery_code} />
            </Field>
        {/if}

        <SubmitButton class="w-full" submitting={form.processing}>Confirm</SubmitButton>

        <Button
            variant="ghost"
            class="w-full"
            type="button"
            onclick={() => {
                useRecovery = !useRecovery;
                form.reset('code', 'recovery_code');
            }}>
            {useRecovery ? 'Use an authentication code' : 'Use a recovery code'}
        </Button>
    </form>
</AuthLayout>
