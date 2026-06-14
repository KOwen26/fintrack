<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import authPassword from '@wayfinder/routes/auth/password';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    const form = useForm({
        password: '',
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();

        form.post(authPassword.confirm().url, {
            onFinish: () => {
                form.reset();
            },
        });
    };
</script>

<AuthLayout>
    <h1>Confirm Password</h1>
    <p class="text-base-content/70 text-sm">Please confirm your password before continuing.</p>
    <form class="mt-6 space-y-4" {onsubmit}>
        <Field title="Password" error={form.errors.password}>
            <PasswordInput name="password" bind:value={form.password} />
        </Field>

        <SubmitButton class="w-full" submitting={form.processing}>Confirm Password</SubmitButton>
    </form>
</AuthLayout>
