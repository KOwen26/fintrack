<script lang="ts">
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    import AppIcon from '@components/ui/app-icon.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';

    interface Props extends RestProps {
        ctaUrl: string;
    }

    let { ctaUrl, class: _class, children, ...props }: Props = $props();
</script>

<ResponsiveCard
    class={cn('dashboard-welcome-card flex flex-col items-center py-16 text-center', _class)}
    contentClass="text-center"
    {...props}>
    <div class="mb-6 flex w-full items-center justify-center rounded-2xl text-center">
        <AppIcon class="size-40" />
    </div>

    <h3 class="mb-2 text-xl font-semibold text-foreground">Welcome to FinTrack</h3>
    <p class="mb-6 max-w-sm text-sm text-foreground/70">
        Get started by creating your first account. Track your spending, set budgets, and stay on
        top of your finances.
    </p>

    <Button color="primary" href={ctaUrl}>
        <i class="iconify size-5 solar--add-bold-duotone"></i>
        Create Account
    </Button>
</ResponsiveCard>
