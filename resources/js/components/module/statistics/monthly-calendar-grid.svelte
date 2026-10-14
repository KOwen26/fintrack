<script lang="ts">
    /**
     * Calendar grid for the monthly flow chart. Owns LayerChart's Month mark
     * and derives the cell size from the Chart's own measured container width
     * (the same reactive signal Month uses for monthsPerRow) — so the grid
     * fills the available width without a second ResizeObserver. Repositions
     * LayerChart's Sunday-first columns to Monday-first (matching
     * DatePeriodPreset's week semantics) and adds the weekday header row Month
     * does not render.
     */
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
        fillFor: (cell: CalendarCell) => string;
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

    // Text scales with the cells so proportions hold at any container width.
    const weekdaySize = $derived(Math.max(9, Math.round(cellSize * 0.2)));
    const dayNumberSize = $derived(Math.max(10, Math.round(cellSize * 0.28)));
    const cornerNumberSize = $derived(Math.max(8, Math.round(cellSize * 0.2)));
    const amountSize = $derived(Math.max(9, Math.round(cellSize * 0.26)));
</script>

<Layer>
    <Month {cellSize} end={monthEnd} monthLabel={false} showDayNumber={false} start={monthStart}>
        {#snippet children({ cells })}
            {#each WEEKDAYS as label, i (label)}
                <text
                    style="font-size: {weekdaySize}px"
                    class="fill-muted-foreground/70 font-medium uppercase"
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

                <rect
                    class={cn(onDayClick && 'cursor-pointer')}
                    aria-label={cell.date.toDateString()}
                    fill={fillFor(cell)}
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
                        style="font-size: {cornerNumberSize}px"
                        class="pointer-events-none fill-foreground/60 font-medium tabular-nums select-none"
                        dominant-baseline="middle"
                        text-anchor="start"
                        x={x + cellSize * 0.16}
                        y={y + cellSize * 0.28}>
                        {cell.date.getDate()}
                    </text>

                    <text
                        style="font-size: {amountSize}px"
                        class="pointer-events-none fill-foreground/80 font-semibold tabular-nums select-none"
                        dominant-baseline="middle"
                        text-anchor="middle"
                        x={x + cellSize / 2}
                        y={y + cellSize * 0.66}>
                        {label}
                    </text>
                {:else}
                    <text
                        style="font-size: {dayNumberSize}px"
                        class="pointer-events-none fill-foreground/70 font-medium tabular-nums select-none"
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
