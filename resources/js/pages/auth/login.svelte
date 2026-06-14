<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import auth from '@wayfinder/routes/auth';
    import authPassword from '@wayfinder/routes/auth/password';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Button from '@components/ui/button.svelte';
    import Checkbox from '@components/ui/forms/checkbox.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import PasswordInput from '@components/ui/forms/password-input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let { canResetPassword, status }: { canResetPassword: boolean; status: string | null } =
        $props();

    const form = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();

        form.post(auth.login().url, {
            onFinish: () => form.reset('password'),
        });
    };
</script>

<AuthLayout>
    <h1>Login</h1>
    {#if status}
        <p class="text-success text-sm">{status}</p>
    {/if}
    <form class="space-y-5" {onsubmit}>
        <Field error={form.errors.email} title="Email">
            <Input name="email" autocomplete="email" type="email" bind:value={form.email} />
        </Field>

        <Field error={form.errors.password} title="Password">
            <PasswordInput name="password" bind:value={form.password} />
        </Field>

        <div class="grid grid-cols-2 items-end">
            <Checkbox name="remember" bind:checked={form.remember}>Ingat Saya</Checkbox>

            <div class="text-end">
                {#if canResetPassword}
                    <Button href={authPassword.request().url} variant="link">
                        Lupa Password ?
                    </Button>
                {/if}
            </div>
        </div>

        <SubmitButton class="w-full" submitting={form.processing}>Login</SubmitButton>
    </form>
</AuthLayout>
