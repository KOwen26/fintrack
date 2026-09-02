<script lang="ts">
    import type { RestProps } from '@type/index';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        totalBalance: number | null;
        monthlyIncome: number;
        monthlyExpenses: number;
        loading?: boolean;
    }

    let {
        totalBalance,
        monthlyIncome,
        monthlyExpenses,
        loading = false,
        class: _class,
        children,
        ...props
    }: Props = $props();
</script>

<div
    class={cn(
        'hero-card rounded-xl p-5 text-white lg:p-6',
        'bg-linear-to-br from-primary to-primary/70',
        _class
    )}
    {...props}>
    {#if loading}
        <div class="animate-pulse space-y-4">
            <div class="flex items-center justify-between">
                <div class="h-4 w-24 rounded bg-white/20">&nbsp;</div>
                <div class="h-5 w-20 rounded-full bg-white/15">&nbsp;</div>
            </div>
            <div class="h-10 w-48 rounded bg-white/20">&nbsp;</div>
            <div class="flex gap-4">
                <div class="h-4 w-32 rounded bg-white/15">&nbsp;</div>
                <div class="h-4 w-28 rounded bg-white/15">&nbsp;</div>
            </div>
        </div>
    {:else}
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-white/85">Total Balance</span>
            <span
                class="rounded-full bg-white/15 px-2 py-0.5 text-[10px] font-medium tracking-wider text-white/80 uppercase">
                All Accounts
            </span>
        </div>

        <p class="mt-1 text-3xl font-bold tracking-tight lg:text-4xl">
            {Formatter.currency(totalBalance ?? 0)}
        </p>

        <div class="mt-3 flex items-center gap-4 text-sm">
            <span class="flex items-center gap-1">
                <i class="iconify size-4 text-success-300 shadow solar--course-up-bold-duotone"></i>
                <span class="text-white/85">+ {Formatter.currency(monthlyIncome)}</span>
                <span class="text-white/60">this month</span>
            </span>
            <span class="flex items-center gap-1">
                <i class="iconify size-4 text-error-300 shadow solar--course-down-bold-duotone"></i>
                <span class="text-white/85">- {Formatter.currency(monthlyExpenses)}</span>
                <span class="text-white/60">this month</span>
            </span>
        </div>
    {/if}
</div>
