<script lang="ts" module>
    /** A cell's visual pair — background plus the foreground readable on it. */
    export interface CalendarBackground {
        /** Cell background — applied as the SVG fill / CSS background. */
        background: string;
        /** Foreground for the day number + amount on top of that background. */
        foreground: string;
    }

    /**
     * Expand a color anchor into the 4 intensity steps — tints mix onto the
     * card surface, the top step is the anchor at full strength with its
     * companion foreground. Anchors arrive as chart-config vars (`var(--color-*)`)
     * so each chart instance owns its palette through the config prop.
     */
    export function calendarRamp(anchor: string, foreground: string): CalendarBackground[] {
        return [25, 50, 75, 100].map((pct) => ({
            background:
                pct === 100 ? anchor : `color-mix(in oklab, ${anchor} ${pct}%, var(--card))`,
            foreground: pct === 100 ? foreground : 'var(--foreground)',
        }));
    }
</script>

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
        backgroundFor,
        labelFor,
        onDayClick,
    }: {
        monthStart: Date;
        monthEnd: Date;
        backgroundFor: (cell: CalendarCell) => CalendarBackground;
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
                {@const background = backgroundFor(cell)}

                <!-- svelte-ignore a11y_click_events_have_key_events -->
                <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
                <rect
                    class={cn(onDayClick && 'cursor-pointer')}
                    aria-label={cell.date.toDateString()}
                    fill={background.background}
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
                        style:fill={background.foreground}
                        class="pointer-events-none text-xs font-medium tabular-nums select-none"
                        dominant-baseline="middle"
                        opacity="0.75"
                        text-anchor="start"
                        x={x + cellSize * 0.16}
                        y={y + cellSize * 0.28}>
                        {cell.date.getDate()}
                    </text>

                    <text
                        style:fill={background.foreground}
                        class="pointer-events-none text-xs font-semibold tabular-nums select-none"
                        dominant-baseline="middle"
                        text-anchor="middle"
                        x={x + cellSize / 2}
                        y={y + cellSize * 0.66}>
                        {label}
                    </text>
                {:else}
                    <text
                        style:fill={background.foreground}
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
