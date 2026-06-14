<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import authVerification from '@wayfinder/routes/auth/verification';

    import AuthLayout from '@components/layouts/auth-layout.svelte';
    import Button from '@components/ui/button.svelte';

    let { status }: { status: string | null } = $props();

    const resend = () => {
        router.post(authVerification.send().url);
    };
</script>

<AuthLayout>
    <h1>Email Verification</h1>
    <p class="text-base-content/70 text-sm">
        Thanks for signing up! Before getting started, please verify your email address by clicking
        the link we sent you. If you didn't receive the email, we'll gladly send another.
    </p>
    {#if status === 'verification-link-sent'}
        <p class="text-success mt-4 text-sm">
            A new verification link has been sent to your email address.
        </p>
    {/if}
    <div class="mt-6">
        <Button class="w-full" onclick={resend}>Resend Verification Email</Button>
    </div>
</AuthLayout>
