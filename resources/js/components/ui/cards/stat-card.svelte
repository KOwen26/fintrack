<script lang="ts">
    import type { RestProps } from '@type/index';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        label: string;
        value: number | string | null;
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
        icon: string;
        format?: 'currency' | 'number';

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
        format = 'currency',
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

    const KNOWN_ICONS: Record<string, string> = {
        'trending-up': 'solar--course-up-bold-duotone',
        'trending-down': 'solar--course-down-bold-duotone',
        'piggy-bank': 'solar--safe-2-bold-duotone',
        clock: 'solar--clock-circle-bold-duotone',
    };

    let iconClass = $derived(() => KNOWN_ICONS[icon] ?? icon);

    let formattedValue = $derived(() => {
        if (value === null || value === undefined) return null;
        if (typeof value === 'string') return value;
        if (format === 'number') return value.toLocaleString();

        return Formatter.currency(value);
    });
</script>

<div
    class={cn(
        'rounded-xl p-4',
        'bg-card text-card-foreground',
        'border border-base-content/25',
        'transition-shadow hover:shadow-sm',
        _class
    )}
    {...props}>
    {#if loading}
        <div class="animate-pulse space-y-3">
            <div class="flex items-center justify-between">
                <div class="h-3 w-16 rounded bg-base-content/10"></div>
                <div class="h-8 w-8 rounded-lg bg-base-content/10"></div>
            </div>
            <div class="h-7 w-28 rounded bg-base-content/10"></div>
            <div class="h-3 w-24 rounded bg-base-content/10"></div>
        </div>
    {:else}
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium tracking-wider text-base-content/60 uppercase">
                {label}
            </span>
            <div
                class={cn(
                    'flex size-8 items-center justify-center rounded-lg',
                    COLOR_STYLES[color].bg
                )}>
                <i class={cn('iconify size-4 shrink-0', iconClass(), COLOR_STYLES[color].fg)}></i>
            </div>
        </div>

        <p class="mt-3 text-2xl font-bold tracking-tight text-base-content">
            {formattedValue() ?? '—'}
        </p>

        {#if trend}
            <p
                class={cn(
                    'mt-1 text-xs',
                    trend.direction === 'up' ? COLOR_STYLES[color].fg : 'text-base-content/40'
                )}>
                {trend.label}
            </p>
        {/if}
    {/if}
</div>
