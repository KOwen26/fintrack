<script lang="ts">
    import type { RestProps } from '@type/index';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        label: string;
        value: number | null;
        color:
            | 'primary'
            | 'secondary'
            | 'accent'
            | 'success'
            | 'info'
            | 'warning'
            | 'error'
            | 'light'
            | 'dark';
        icon: 'trending-up' | 'trending-down' | 'piggy-bank' | 'clock';

        trend?: {
            direction: 'up' | 'down';
            value: number;
            label: string;
        } | null;

        loading?: boolean;
    }

    let {
        label,
        value,
        color,
        icon,
        trend = null,
        loading = false,
        class: _class,
        children,
        ...props
    }: Props = $props();

    const COLOR_STYLES: Record<string, { bg: string; fg: string }> = {
        primary: { bg: 'bg-primary/10', fg: 'text-primary' },
        secondary: { bg: 'bg-secondary/10', fg: 'text-secondary' },
        accent: { bg: 'bg-accent/10', fg: 'text-accent' },
        success: { bg: 'bg-success/10', fg: 'text-success' },
        info: { bg: 'bg-info/10', fg: 'text-info' },
        warning: { bg: 'bg-warning/10', fg: 'text-warning' },
        error: { bg: 'bg-error/10', fg: 'text-error' },
        light: { bg: 'bg-base-200/10', fg: 'text-base-content' },
        dark: { bg: 'bg-base-300/10', fg: 'text-base-content' },
    };

    const ICON_MAP: Record<string, string> = {
        'trending-up': 'ph--trend-up-bold',
        'trending-down': 'ph--trend-down-bold',
        'piggy-bank': 'ph--piggy-bank-bold',
        clock: 'ph--clock-bold',
    };
</script>

<div
    class={cn(
        'stat-card rounded-xl p-4',
        'bg-card text-card-foreground',
        'border border-base-content/25',
        'transition-shadow hover:shadow-sm',
        _class
    )}
    {...props}>
    {#if loading}
        <div class="animate-pulse space-y-3">
            <div class="flex items-center justify-between">
                <div class="h-3 w-16 rounded bg-base-content/10">&nbsp;</div>
                <div class="h-8 w-8 rounded-lg bg-base-content/10">&nbsp;</div>
            </div>
            <div class="h-7 w-28 rounded bg-base-content/10">&nbsp;</div>
            <div class="h-3 w-24 rounded bg-base-content/10">&nbsp;</div>
        </div>
    {:else}
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium uppercase tracking-wider text-base-content/60">
                {label}
            </span>
            <div
                class={cn(
                    'flex size-8 items-center justify-center rounded-lg',
                    COLOR_STYLES[color].bg
                )}>
                <i
                    class={cn(
                        'iconify shrink-0 size-4',
                        ICON_MAP[icon] ?? ICON_MAP['clock'],
                        COLOR_STYLES[color].fg
                    )}></i>
            </div>
        </div>

        <p class="mt-3 text-xl font-bold tracking-tight text-base-content">
            {Formatter.currency(value ?? 0)}
        </p>

        {#if trend}
            <p
                class={cn(
                    'mt-1 text-xs',
                    trend.direction === 'up' ? COLOR_STYLES[color].fg : 'text-base-content/40'
                )}>
                {trend.label}
            </p>
        {:else}
            <p class="mt-1 text-xs text-base-content/40">&nbsp;</p>
        {/if}
    {/if}
</div>
