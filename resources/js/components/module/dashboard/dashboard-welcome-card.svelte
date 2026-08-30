<script lang="ts">
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        ctaUrl: string;
    }

    let { ctaUrl, class: _class, children, ...props }: Props = $props();
</script>

<Card
    class={cn('dashboard-welcome-card flex flex-col items-center py-16 text-center', _class)}
    {...props}>
    <div
        style="background: var(--color-base-200);"
        class="mb-6 flex size-16 items-center justify-center rounded-2xl">
        <i class="iconify size-8 text-base-content/40 ph--bank-bold"></i>
    </div>

    <h3 class="mb-2 text-xl font-semibold text-base-content">Welcome to FinTrack</h3>
    <p class="mb-6 max-w-sm text-sm text-base-content/60">
        Get started by creating your first account. Track your spending, set budgets, and stay on
        top of your finances.
    </p>

    <Button color="primary" href={ctaUrl}>
        <i class="iconify size-5 ph--plus-bold"></i>
        Create Account
    </Button>
</Card>
