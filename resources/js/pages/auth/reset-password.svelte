<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import authPassword from '@wayfinder/routes/auth/password';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let { user }: { user?: { email: string; token: string } } = $props();

    const form = useForm({
        email: user?.email,
        token: user?.token,
        password: '',
        password_confirmation: '',
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();

        form.post(authPassword.store().url, {
            onFinish: () => {
                form.reset('password', 'password_confirmation');
            },
        });
    };
</script>

<AuthLayout>
    <h1>Konfirmasi Password Baru</h1>
    <form class="space-y-4" {onsubmit}>
        <Field title="Email" error={form.errors.email}>
            <Input
                name="email"
                autocomplete="email"
                readonly
                type="email"
                bind:value={form.email} />
        </Field>

        <Field title="Password" error={form.errors.password}>
            <PasswordInput name="password" bind:value={form.password} />
        </Field>

        <Field title="Konfirmasi Password" error={form.errors.password_confirmation}>
            <PasswordInput name="password_confirmation" bind:value={form.password_confirmation} />
        </Field>

        <SubmitButton class="w-full" submitting={form.processing}>
            Kirim Email Reset Password
        </SubmitButton>
    </form>
</AuthLayout>
