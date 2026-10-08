<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import auth from '@wayfinder/routes/auth';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Button from '@components/ui/button.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';
    import Separator from '@components/ui/separator.svelte';

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
    <form class="flex flex-col gap-5" {onsubmit}>
        <Field error={form.errors.name} title="Name">
            <Input name="name" placeholder="Your name" type="text" bind:value={form.name} />
        </Field>

        <Field error={form.errors.email} title="Email">
            <Input
                name="email"
                autocomplete="email"
                placeholder="email@example.com"
                type="email"
                bind:value={form.email} />
        </Field>

        <Field error={form.errors.password} title="Password">
            <PasswordInput name="password" bind:value={form.password} />
        </Field>

        <Field error={form.errors.password_confirmation} title="Password Confirmation">
            <PasswordInput name="password_confirmation" bind:value={form.password_confirmation} />
        </Field>

        <div class="mt-5 space-y-3">
            <SubmitButton class="w-full" submitting={form.processing}>Register</SubmitButton>

            <Separator />

            <Button class="w-full" color="secondary" href={auth.login.url()} variant="outline"
                >Login</Button>
        </div>
    </form>
</AuthLayout>
