<script lang="ts">
    import type { WithoutChildrenOrChild } from '@utilities/shadcn.js';

    import { Calendar as CalendarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        value,
        onchange,
        ...restProps
    }: WithoutChildrenOrChild<CalendarPrimitive.MonthSelectProps> = $props();
</script>

<span
    class={cn(
        'has-focus:border-ring border-input has-focus:ring-ring/50 relative flex rounded-md border shadow-xs has-focus:ring-[3px]',
        className
    )}>
    <CalendarPrimitive.MonthSelect class="absolute inset-0 opacity-0" bind:ref {...restProps}>
        {#snippet child({ props, monthItems, selectedMonthItem })}
            <select {...props} {onchange} {value}>
                {#each monthItems as monthItem (monthItem.value)}
                    <option
                        selected={value !== undefined
                            ? monthItem.value === value
                            : monthItem.value === selectedMonthItem.value}
                        value={monthItem.value}>
                        {monthItem.label}
                    </option>
                {/each}
            </select>
            <span
                class="[&>svg]:text-muted-foreground flex h-8 items-center gap-1 rounded-md pr-1 pl-2 text-sm font-medium select-none [&>svg]:size-3.5"
                aria-hidden="true">
                {monthItems.find((item) => item.value === value)?.label || selectedMonthItem.label}
                <i class="iconify ph--caret-down-duotone size-4"></i>
            </span>
        {/snippet}
    </CalendarPrimitive.MonthSelect>
</span>
