<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';

    import { Calendar as CalendarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        value,
        ...restProps
    }: WithoutChildrenOrChild<CalendarPrimitive.YearSelectProps> = $props();
</script>

<span
    class={cn(
        'has-focus:border-ring border-input has-focus:ring-ring/50 relative flex rounded-md border shadow-xs has-focus:ring-[3px]',
        className
    )}>
    <CalendarPrimitive.YearSelect class="absolute inset-0 opacity-0" bind:ref {...restProps}>
        {#snippet child({ props, yearItems, selectedYearItem })}
            <select {...props} {value}>
                {#each yearItems as yearItem (yearItem.value)}
                    <option
                        selected={value !== undefined
                            ? yearItem.value === value
                            : yearItem.value === selectedYearItem.value}
                        value={yearItem.value}>
                        {yearItem.label}
                    </option>
                {/each}
            </select>
            <span
                class="[&>svg]:text-muted-foreground flex h-8 items-center gap-1 rounded-md pr-1 pl-2 text-sm font-medium select-none [&>svg]:size-3.5"
                aria-hidden="true">
                {yearItems.find((item) => item.value === value)?.label || selectedYearItem.label}
                <i class="iconify ph--caret-down-duotone size-4"></i>
            </span>
        {/snippet}
    </CalendarPrimitive.YearSelect>
</span>
