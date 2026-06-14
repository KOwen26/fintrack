<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import auth from '@wayfinder/routes/auth';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    const form = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();

        form.post(auth.register().url);
    };
</script>

<AuthLayout>
    <h1>Register</h1>
    <form class="space-y-4" {onsubmit}>
        <Field title="Name" error={form.errors.name}>
            <Input name="name" type="text" placeholder="Your name" bind:value={form.name} />
        </Field>

        <Field title="Email" error={form.errors.email}>
            <Input
                name="email"
                type="email"
                autocomplete="email"
                placeholder="email@example.com"
                bind:value={form.email} />
        </Field>

        <Field title="Password" error={form.errors.password}>
            <PasswordInput name="password" bind:value={form.password} />
        </Field>

        <Field title="Password Confirmation" error={form.errors.password_confirmation}>
            <PasswordInput name="password_confirmation" bind:value={form.password_confirmation} />
        </Field>

        <SubmitButton class="w-full" submitting={form.processing}>Register</SubmitButton>
    </form>
</AuthLayout>
