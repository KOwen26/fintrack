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
        'relative flex rounded-md border border-input shadow-xs has-focus:border-ring has-focus:ring-[3px] has-focus:ring-ring/50',
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
                class="flex h-8 items-center gap-1 rounded-md pr-1 pl-2 text-sm font-medium select-none [&>svg]:size-3.5 [&>svg]:text-muted-foreground"
                aria-hidden="true">
                {monthItems.find((item) => item.value === value)?.label || selectedMonthItem.label}
                <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
            </span>
        {/snippet}
    </CalendarPrimitive.MonthSelect>
</span>
