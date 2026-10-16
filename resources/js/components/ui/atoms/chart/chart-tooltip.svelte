<script lang="ts">
    import type { TooltipPayload } from './chart-utils.js';
    import type { WithElementRef, WithoutChildren } from '@utilities/shadcn.js';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { getPayloadConfigFromPayload, useChart } from './chart-utils.js';

    import { getChartContext, Tooltip as TooltipPrimitive } from 'layerchart';

    import { cn } from '@utilities/shadcn.js';

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    function defaultFormatter(value: any, _payload: TooltipPayload[]) {
        return `${value}`;
    }

    let {
        ref = $bindable(null),
        class: className,
        hideLabel = false,
        indicator = 'dot',
        hideIndicator = false,
        labelKey,
        label,
        labelFormatter = defaultFormatter,
        labelClassName,
        formatter,
        nameKey,
        color,
        tooltipContent,
        ...restProps
    }: WithoutChildren<WithElementRef<HTMLAttributes<HTMLDivElement>>> & {
        hideLabel?: boolean;
        label?: string;
        indicator?: 'line' | 'dot' | 'dashed';
        nameKey?: string;
        labelKey?: string;
        hideIndicator?: boolean;
        labelClassName?: string;
        labelFormatter?:
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            ((value: any, payload: TooltipPayload[]) => string | number | Snippet) | null;
        formatter?: Snippet<
            [
                {
                    value: unknown;
                    name: string;
                    item: TooltipPayload;
                    index: number;
                    payload: TooltipPayload[];
                },
            ]
        >;
        // Full tooltip body override — always wins over the series rendering.
        // Receives the hovered datum (fed via `chart.tooltip.show(event, data)`)
        // and a styling kit: house-rendered `label` and `row` snippets so
        // custom content matches the built-in look without copying classes.
        // A row's value may be a plain string or a snippet (rendered into the
        // value slot) for richer content like CurrencyAmount.
        tooltipContent?: Snippet<
            [
                data: any,
                ui: {
                    label: Snippet<[text?: string]>;
                    row: Snippet<[label: string, value: string | Snippet]>;
                },
            ]
        >;
    } = $props();

    const chart = useChart();
    const chartCtx = getChartContext();

    // Filter to series with defined values (important for item-based charts like Pie/Arc
    // where only the hovered item has a value)
    const visibleSeries = $derived(
        chartCtx.tooltip.series.filter((s: TooltipPayload) => s.value !== undefined)
    );

    const formattedLabel = $derived.by(() => {
        if (hideLabel || !visibleSeries?.length) return null;

        const [item] = visibleSeries;
        const tooltipData = chartCtx.tooltip.data;

        // Get the x-axis label value from the raw tooltip data (e.g. a Date or month string)
        const dataLabel = tooltipData != null ? chartCtx.x(tooltipData) : undefined;

        const key = labelKey ?? item?.label ?? item?.key ?? 'value';
        const itemConfig = getPayloadConfigFromPayload(
            chart.config,
            item,
            key,
            tooltipData as Record<string, unknown> | null
        );

        let value: unknown;
        if (!labelKey && typeof label === 'string') {
            value = chart.config[label as keyof typeof chart.config]?.label ?? label;
        } else if (labelKey) {
            value = itemConfig?.label ?? dataLabel;
        } else {
            value = dataLabel;
        }

        if (value === undefined) return null;
        if (!labelFormatter) return value;

        return labelFormatter(value, visibleSeries);
    });

    const nestLabel = $derived(visibleSeries.length === 1 && indicator !== 'dot');
</script>

<TooltipPrimitive.Root variant="none">
    <div
        bind:this={ref}
        class={cn(
            'grid min-w-32 items-start gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl',
            className
        )}
        {...restProps}>
        {#if tooltipContent && chartCtx.tooltip.data != null}
            {@render tooltipContent(chartCtx.tooltip.data, {
                label: TooltipLabel,
                row: TooltipRow,
            })}
        {:else if visibleSeries?.length}
            {#if !nestLabel}
                {@render TooltipLabel()}
            {/if}
            <div class="grid gap-1.5">
                {#each visibleSeries as item, i (item.key + i)}
                    {@const key = `${nameKey || item.key || item.label || 'value'}`}
                    {@const itemConfig = getPayloadConfigFromPayload(
                        chart.config,
                        item,
                        key,
                        chartCtx.tooltip.data
                    )}
                    {@const indicatorColor = color || item.config?.color || item.color}
                    <div
                        class={cn(
                            'flex w-full flex-wrap items-stretch gap-2 [&>svg]:h-2.5 [&>svg]:w-2.5 [&>svg]:text-muted-foreground',
                            indicator === 'dot' && 'items-center'
                        )}>
                        {#if formatter && item.value !== undefined && item.label}
                            {@render formatter({
                                value: item.value,
                                name: item.label,
                                item,
                                index: i,
                                payload: visibleSeries,
                            })}
                        {:else}
                            {#if itemConfig?.icon}
                                <itemConfig.icon />
                            {:else if !hideIndicator}
                                <div
                                    style="--color-bg: {indicatorColor}; --color-border: {indicatorColor};"
                                    class={cn(
                                        'shrink-0 rounded-[2px] border-(--color-border) bg-(--color-bg)',
                                        {
                                            'h-2.5 w-2.5': indicator === 'dot',
                                            'h-full w-1': indicator === 'line',
                                            'w-0 border-[1.5px] border-dashed bg-transparent':
                                                indicator === 'dashed',
                                            'my-0.5': nestLabel && indicator === 'dashed',
                                        }
                                    )}>
                                </div>
                            {/if}
                            <div
                                class={cn(
                                    'flex flex-1 shrink-0 justify-between leading-none',
                                    nestLabel ? 'items-end' : 'items-center'
                                )}>
                                <div class="grid gap-1.5">
                                    {#if nestLabel}
                                        {@render TooltipLabel()}
                                    {/if}
                                    <span class="text-muted-foreground">
                                        {itemConfig?.label || item.label}
                                    </span>
                                </div>
                                {#if item.value !== undefined}
                                    <span
                                        class="font-mono font-medium text-foreground tabular-nums">
                                        {item.value.toLocaleString()}
                                    </span>
                                {/if}
                            </div>
                        {/if}
                    </div>
                {/each}
            </div>
        {/if}
    </div>
</TooltipPrimitive.Root>

{#snippet TooltipLabel(text?: string)}
    {@const value = text ?? formattedLabel}

    {#if value}
        <div class={cn('font-medium', labelClassName)}>
            {#if typeof value === 'function'}
                {@render value()}
            {:else}
                {value}
            {/if}
        </div>
    {/if}
{/snippet}

{#snippet TooltipRow(label: string, value: string | Snippet)}
    <div class="flex w-full flex-wrap items-center justify-between gap-2 leading-none">
        <span class="text-muted">{label}</span>
        {#if typeof value === 'function'}
            {@render value?.()}
        {:else}
            <span class="font-mono font-medium text-foreground tabular-nums">{value}</span>
        {/if}
    </div>
{/snippet}
