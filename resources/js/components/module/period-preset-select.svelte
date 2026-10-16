<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    import DatePeriodPreset, { DatePeriodPresetMeta } from '@wayfinder/App/Enums/DatePeriodPreset';

    export type PeriodPreset = App.Enums.DatePeriodPreset;

    /** Which preset family the selector offers. */
    export type PresetScope = 'all' | 'monthly';

    /** Presets rendered as always-visible segments; everything else collapses into the dropdown. */
    const DEFAULT_QUICK_PICKS: PeriodPreset[] = [
        DatePeriodPreset.ThisMonth,
        DatePeriodPreset.Last30Days,
        DatePeriodPreset.Last3Months,
    ];

    /** The full offered universe — every preset the enum exposes. */
    const ALL_PRESETS: PeriodPreset[] = Object.values(DatePeriodPreset);

    /** Month-granular presets only — no day/week/rolling-day windows, no unbounded All. */
    const MONTHLY_PRESETS: PeriodPreset[] = [
        DatePeriodPreset.ThisMonth,
        DatePeriodPreset.LastMonth,
        DatePeriodPreset.ThisYear,
        DatePeriodPreset.LastYear,
        DatePeriodPreset.Last3Months,
        DatePeriodPreset.Last6Months,
        DatePeriodPreset.Last12Months,
    ];

    /** Preset families the selector can offer, keyed by the `preset` prop. */
    const PRESET_SETS: Record<PresetScope, PeriodPreset[]> = {
        all: ALL_PRESETS,
        monthly: MONTHLY_PRESETS,
    };

    /**
     * Dropdown groups. `YearOnYear` is intentionally omitted — it resolves to the
     * identical period as `Last12Months` (see DatePeriodPreset::toPeriod()).
     */
    const OVERFLOW_GROUPS: { label: string; presets: PeriodPreset[] }[] = [
        {
            label: 'Day',
            presets: [DatePeriodPreset.Today, DatePeriodPreset.Yesterday, DatePeriodPreset.All],
        },
        {
            label: 'Calendar',
            presets: [
                DatePeriodPreset.ThisWeek,
                DatePeriodPreset.ThisMonth,
                DatePeriodPreset.ThisYear,
                DatePeriodPreset.LastMonth,
                DatePeriodPreset.LastYear,
            ],
        },
        {
            label: 'Rolling',
            presets: [
                DatePeriodPreset.Last7Days,
                DatePeriodPreset.Last14Days,
                DatePeriodPreset.Last3Months,
                DatePeriodPreset.Last6Months,
                DatePeriodPreset.Last12Months,
            ],
        },
    ];
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn';

    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu';
    import ToggleGroupItem from '@components/ui/forms/toggle-group-item.svelte';
    import ToggleGroup from '@components/ui/forms/toggle-group.svelte';

    interface Props {
        value?: PeriodPreset;
        /** Which preset family to offer — 'monthly' shows only month-granular values. */
        preset?: PresetScope;
        quickPicks?: PeriodPreset[];
        /** Dropdown-only — no segments; the trigger shows the selected label and the dropdown offers the full universe. */
        withoutQuickPicks?: boolean;
        onValueChange?: (preset: PeriodPreset) => void;
        labelVariant?: 'default' | 'short';
        /** Dropdown content flow: 1 column (default) or 2 columns with groups as cells. */
        columns?: 1 | 2;
        /** Also list quick picks inside the dropdown (they are deduped by default). */
        showQuickPicksInDropdown?: boolean;
        class?: string;
    }

    let {
        value = $bindable(DatePeriodPreset.ThisMonth),
        preset = 'all',
        quickPicks = DEFAULT_QUICK_PICKS,
        withoutQuickPicks = false,
        onValueChange,
        labelVariant = 'default',
        columns = 1,
        showQuickPicksInDropdown = false,
        class: _class,
    }: Props = $props();

    const labelOf = $derived(
        (scope: PeriodPreset): string =>
            DatePeriodPresetMeta[scope][labelVariant === 'short' ? 'labelShort' : 'label']
    );

    const offered = $derived(new Set(PRESET_SETS[preset]));

    // Dropdown-only mode forces no segments; quick picks outside the
    // offered universe vanish from the segments otherwise.
    const segments = $derived(
        withoutQuickPicks ? [] : quickPicks.filter((preset) => offered.has(preset))
    );

    const overflowGroups = $derived(
        OVERFLOW_GROUPS.map((group) => ({
            ...group,
            presets: group.presets.filter(
                (preset) =>
                    offered.has(preset) &&
                    // Dropdown-only must not lose quick picks — segments are gone,
                    // so the dropdown carries the full offered universe.
                    (withoutQuickPicks || showQuickPicksInDropdown || !quickPicks.includes(preset))
            ),
        })).filter((group) => group.presets.length > 0)
    );

    function select(preset: PeriodPreset): void {
        value = preset;
        onValueChange?.(preset);
    }
</script>

<div
    class={cn(
        'inline-flex items-stretch overflow-hidden rounded-md border border-input shadow-xs',
        _class
    )}>
    <ToggleGroup
        class="rounded-none"
        onValueChange={(v) => {
            // Re-clicking the active segment would deselect; a period is always set.
            if (v) select(v as PeriodPreset);
        }}
        size="sm"
        type="single"
        {value}
        variant="default">
        {#if withoutQuickPicks}
            <ToggleGroupItem class="pointer-events-none rounded-none! px-3" value={preset}>
                {labelOf(value)}
            </ToggleGroupItem>
        {:else}
            {#each segments as preset (preset)}
                <ToggleGroupItem class="rounded-none! px-3" value={preset}>
                    {labelOf(preset)}
                </ToggleGroupItem>
            {/each}
        {/if}
    </ToggleGroup>

    <DropdownMenu.DropdownMenu>
        <DropdownMenu.Trigger
            class={cn(
                'inline-flex items-center justify-center border-l border-input px-2 text-sm font-medium text-muted-foreground transition-[color,box-shadow] hover:bg-accent hover:text-accent-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:pointer-events-none disabled:opacity-50'
            )}
            aria-label="More periods">
            <i class="iconify size-4 solar--alt-arrow-down-line-duotone"></i>
        </DropdownMenu.Trigger>

        <DropdownMenu.Content class={cn('min-w-fit', columns === 2 && 'w-max')} align="end">
            <DropdownMenu.RadioGroup
                class={columns === 2 ? 'columns-2 gap-y-1' : undefined}
                onValueChange={(v) => select(v as PeriodPreset)}
                {value}>
                {#each overflowGroups as group, index (group.label)}
                    <!-- bits-ui links radio items via context, so wrapper divs are layout-only, 'break-inside-avoid' -->
                    <div class={columns === 2 ? 'flex flex-col' : 'contents'}>
                        {#if columns === 1 && index > 0}
                            <DropdownMenu.Separator />
                        {/if}
                        <DropdownMenu.Label
                            class="text-xs font-bold tracking-widest text-muted-foreground uppercase">
                            {group.label}
                        </DropdownMenu.Label>
                        {#each group.presets as preset (preset)}
                            <DropdownMenu.RadioItem
                                class="pl-6"
                                checkClass="left-1.5"
                                value={preset}>
                                {labelOf(preset)}
                            </DropdownMenu.RadioItem>
                        {/each}
                    </div>
                {/each}
            </DropdownMenu.RadioGroup>
        </DropdownMenu.Content>
    </DropdownMenu.DropdownMenu>
</div>
