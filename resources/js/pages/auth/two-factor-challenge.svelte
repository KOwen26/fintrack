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
            <Field error={form.errors.code} title="Authentication Code">
                <Input
                    name="code"
                    autocomplete="one-time-code"
                    inputmode="numeric"
                    placeholder="123456"
                    type="text"
                    bind:value={form.code} />
            </Field>
        {:else}
            <Field error={form.errors.recovery_code} title="Recovery Code">
                <Input
                    name="recovery_code"
                    autocomplete="one-time-code"
                    placeholder="xxxx-xxxx"
                    type="text"
                    bind:value={form.recovery_code} />
            </Field>
        {/if}

        <SubmitButton class="w-full" submitting={form.processing}>Confirm</SubmitButton>

        <Button
            class="w-full"
            onclick={() => {
                useRecovery = !useRecovery;
                form.reset('code', 'recovery_code');
            }}
            type="button"
            variant="ghost">
            {useRecovery ? 'Use an authentication code' : 'Use a recovery code'}
        </Button>
    </form>
</AuthLayout>
