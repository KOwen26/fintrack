<script lang="ts">
    import type { RestProps } from '@type/index';

    import { Progress } from 'bits-ui';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        label: string;
        value: number;
        max: number;
        color?: 'default' | 'success' | 'warning' | 'error' | string;
        format?: 'currency' | 'percent' | 'number';
        showValues?: boolean;
    }

    let {
        label,
        value,
        max,
        color = 'default',
        format = 'currency',
        showValues = true,
        class: _class,
        children,
        ...props
    }: Props = $props();

    let percentage = $derived(Math.min((value / max) * 100, 100));
    let isOverBudget = $derived(value > max);

    let resolvedColor = $derived.by(() => {
        if (isOverBudget) return 'var(--color-error)';

        const preset: Record<string, string> = {
            default: 'var(--color-primary)',
            success: 'var(--color-success)',
            warning: 'var(--color-warning)',
            error: 'var(--color-error)',
        };

        return preset[color] ?? color;
    });

    let formattedValue = $derived.by(() => {
        switch (format) {
            case 'currency':
                return Formatter.currency(value, true);
            default:
                return value.toLocaleString();
        }
    });

    let formattedMax = $derived.by(() => {
        switch (format) {
            case 'currency':
                return Formatter.currency(max, true);
            default:
                return max.toLocaleString();
        }
    });
</script>

<div class={cn('space-y-1.5', _class)} {...props}>
    <div class="flex items-center justify-between text-sm">
        <span class="font-medium text-base-content">{label}</span>
        {#if showValues}
            <span
                class={cn(
                    'text-xs',
                    isOverBudget ? 'font-medium text-error' : 'text-base-content/60'
                )}>
                {formattedValue} / {formattedMax}
            </span>
        {/if}
    </div>

    <Progress.Root
        class="h-2 overflow-hidden rounded-full bg-base-content/10"
        max={100}
        value={percentage}>
        <!-- <div
            style="transform: translateX(-{100 - percentage}%); background: {resolvedColor}"
            class="h-full w-full rounded-full transition-all duration-300">
        </div> -->
    </Progress.Root>
</div>
