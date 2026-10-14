<script lang="ts">
    import { getChartContext, Layer, Month } from 'layerchart';

    import { cn } from '@utilities/shadcn';

    interface CalendarCell {
        x: number;
        y: number;
        color: unknown;
        data: any;
        date: Date;
    }

    let {
        monthStart,
        monthEnd,
        fillFor,
        labelFor,
        onDayClick,
    }: {
        monthStart: Date;
        monthEnd: Date;
        fillFor: (cell: CalendarCell) => { fill: string; text: string };
        labelFor?: (cell: CalendarCell) => string | null;
        onDayClick?: (date: Date) => void;
    } = $props();

    const chart = getChartContext();

    // Full-width cells: container width / 7 columns. The floor() slack (≤6px)
    // sits at the right edge; the 28px floor keeps cells from collapsing.
    const cellSize = $derived(chart.width > 0 ? Math.max(28, Math.floor(chart.width / 7)) : 40);

    const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

    // Monday-first slot math. Month's own rows are Sunday-weeks (d3 timeWeek),
    // so relabeling columns alone drops every month-starting Sunday one row —
    // rows are recomputed from the Monday-week slot instead of borrowed.
    /** Blank cells before day 1 when the week starts on Monday. */
    const leading = $derived((monthStart.getDay() + 6) % 7);
</script>

<Layer>
    <Month {cellSize} end={monthEnd} monthLabel={false} showDayNumber={false} start={monthStart}>
        {#snippet children({ cells })}
            {#each WEEKDAYS as label, i (label)}
                <text
                    class="fill-muted-foreground text-sm font-medium uppercase"
                    dominant-baseline="middle"
                    text-anchor="middle"
                    x={i * cellSize + cellSize / 2}
                    y={cellSize / 2}>
                    {label}
                </text>
            {/each}

            {#each cells as cell (cell.date.toISOString())}
                {@const slot = leading + cell.date.getDate() - 1}
                {@const x = (slot % 7) * cellSize}
                {@const y = Math.floor(slot / 7) * cellSize + cellSize}
                {@const fill = fillFor(cell)}

                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                <rect
                    class={cn(onDayClick && 'cursor-pointer')}
                    aria-label={cell.date.toDateString()}
                    fill={fill.fill}
                    height={cellSize - 2}
                    onclick={onDayClick ? () => onDayClick(cell.date) : undefined}
                    onpointerleave={() => chart.tooltip?.hide()}
                    onpointermove={(e: PointerEvent) => chart.tooltip?.show(e, cell.data)}
                    role="img"
                    rx={Math.max(3, Math.round(cellSize / 8))}
                    width={cellSize - 2}
                    x={x + 1}
                    y={y + 1} />

                {@const label = labelFor?.(cell) ?? null}

                {#if label}
                    <!-- day number small in the corner, amount centered below -->
                    <text
                        style:fill={fill.text}
                        class="pointer-events-none text-xs font-medium tabular-nums select-none"
                        dominant-baseline="middle"
                        opacity="0.75"
                        text-anchor="start"
                        x={x + cellSize * 0.16}
                        y={y + cellSize * 0.28}>
                        {cell.date.getDate()}
                    </text>

                    <text
                        style:fill={fill.text}
                        class="pointer-events-none text-xs font-semibold tabular-nums select-none"
                        dominant-baseline="middle"
                        text-anchor="middle"
                        x={x + cellSize / 2}
                        y={y + cellSize * 0.66}>
                        {label}
                    </text>
                {:else}
                    <text
                        style:fill={fill.text}
                        class="pointer-events-none text-sm font-medium tabular-nums select-none"
                        dominant-baseline="middle"
                        text-anchor="middle"
                        x={x + cellSize / 2}
                        y={y + cellSize / 2}>
                        {cell.date.getDate()}
                    </text>
                {/if}
            {/each}
        {/snippet}
    </Month>
</Layer>

<style>
    /* Calendar fill/text steps — CSS-owned so themes or instances can override
       any step without touching JS. Each polarity owns its tint ramp; the top
       step is the full status token with its companion foreground ink. */
    :global(:root) {
        --calendar-income-tint-1: color-mix(in oklab, var(--success) 25%, var(--card));
        --calendar-income-tint-2: color-mix(in oklab, var(--success) 50%, var(--card));
        --calendar-income-tint-3: color-mix(in oklab, var(--success) 75%, var(--card));
        --calendar-income-full: var(--success);

        --calendar-expense-tint-1: color-mix(in oklab, var(--error) 25%, var(--card));
        --calendar-expense-tint-2: color-mix(in oklab, var(--error) 50%, var(--card));
        --calendar-expense-tint-3: color-mix(in oklab, var(--error) 75%, var(--card));
        --calendar-expense-full: var(--error);

        --calendar-none: var(--muted);

        --calendar-text-1: var(--foreground);
        --calendar-text-2: var(--foreground);
        --calendar-text-3: var(--foreground);
        --calendar-income-text-full: var(--success-foreground);
        --calendar-expense-text-full: var(--error-foreground);
        --calendar-none-text: color-mix(in oklab, var(--foreground) 55%, transparent);
    }
</style>
