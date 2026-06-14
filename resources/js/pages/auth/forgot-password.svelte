<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import authPassword from '@wayfinder/routes/auth/password';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import SubmitButton from '@components/ui/forms/submit-button.svelte';

    let { status }: { status: string | null } = $props();

    const form = useForm({
        email: '',
    });

    const onsubmit = (event: SubmitEvent) => {
        event.preventDefault();

        form.post(authPassword.email().url);
    };
</script>

<AuthLayout>
    <h1>Reset Password</h1>
    {#if status}
        <p class="text-success text-sm">{status}</p>
    {/if}
    <form class="space-y-4" {onsubmit}>
        <Field title="Email" error={form.errors.email}>
            <Input name="email" autocomplete="email" type="email" bind:value={form.email} />
        </Field>

        <SubmitButton class="w-full" submitting={form.processing}>
            Kirim Email Reset Password
        </SubmitButton>
    </form>
</AuthLayout>
