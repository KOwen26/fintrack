<script lang="ts" module>
    import type { App } from '@wayfinder/types';

    export type PeriodPreset = App.Enums.DatePeriodPreset;

    /** Which preset family the selector offers. */
    export type PresetScope = 'all' | 'monthly' | 'rolling';

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
        DatePeriodPreset.Last24Months,
        DatePeriodPreset.Last3Years,
        DatePeriodPreset.Last5Years,
    ];

    /** Rolling month-granular windows only — no calendar or day windows, no unbounded All. */
    const ROLLING_PRESETS: PeriodPreset[] = [
        DatePeriodPreset.Last3Months,
        DatePeriodPreset.Last6Months,
        DatePeriodPreset.Last12Months,
        DatePeriodPreset.Last24Months,
        DatePeriodPreset.Last3Years,
        DatePeriodPreset.Last5Years,
    ];

    /** Preset families the selector can offer, keyed by the `preset` prop. */
    const PRESET_SETS: Record<
        PresetScope,
        { presets: PeriodPreset[]; quickPicks: PeriodPreset[] }
    > = {
        all: {
            presets: ALL_PRESETS,
            quickPicks: [
                DatePeriodPreset.ThisMonth,
                DatePeriodPreset.Last30Days,
                DatePeriodPreset.Last3Months,
            ],
        },
        monthly: {
            presets: MONTHLY_PRESETS,
            quickPicks: [
                DatePeriodPreset.ThisMonth,
                DatePeriodPreset.Last3Months,
                DatePeriodPreset.Last6Months,
            ],
        },
        rolling: {
            presets: ROLLING_PRESETS,
            quickPicks: [
                DatePeriodPreset.Last3Months,
                DatePeriodPreset.Last6Months,
                DatePeriodPreset.Last12Months,
            ],
        },
    };

    /**
     * Dropdown groups. `YearOnYear` is intentionally omitted — it resolves to the
     * identical period as `Last12Months` (see DatePeriodPreset::toPeriod()).
     */
    const OVERFLOW_GROUPS: { label: string; presets: PeriodPreset[] }[] = [
        {
            label: 'Rolling',
            presets: [
                DatePeriodPreset.Last7Days,
                DatePeriodPreset.Last14Days,
                DatePeriodPreset.Last3Months,
                DatePeriodPreset.Last6Months,
                DatePeriodPreset.Last12Months,
                DatePeriodPreset.Last24Months,
                DatePeriodPreset.Last3Years,
                DatePeriodPreset.Last5Years,
            ],
        },
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
    ];
</script>

<script lang="ts">
    import DatePeriodPreset, { DatePeriodPresetMeta } from '@wayfinder/App/Enums/DatePeriodPreset';

    import { cn } from '@utilities/shadcn';

    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu';
    import ToggleGroupItem from '@components/ui/forms/toggle-group-item.svelte';
    import ToggleGroup from '@components/ui/forms/toggle-group.svelte';

    interface Props {
        value?: PeriodPreset;
        /** Which preset family to offer — 'monthly' shows only month-granular values, 'rolling' only rolling windows. */
        preset?: PresetScope;
        /**
         * Control shape: 'select' (default) renders quick-pick segments plus an
         * overflow dropdown; 'radio' renders every offered preset as a wrapping
         * chip group — no dropdown, select-only props don't apply.
         */
        variant?: 'select' | 'radio';
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
        variant = 'select',
        quickPicks, // undefined → scope default from PRESET_SETS
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

    const offered = $derived(new Set(PRESET_SETS[preset].presets));

    /** Caller quickPicks override the scope's defaults; scope defaults are guaranteed members of the offered universe. */
    const effectiveQuickPicks = $derived(quickPicks ?? PRESET_SETS[preset].quickPicks);

    // Dropdown-only mode forces no segments; caller-supplied quick picks
    // outside the offered universe still vanish from the segments.
    const segments = $derived(
        withoutQuickPicks ? [] : effectiveQuickPicks.filter((preset) => offered.has(preset))
    );

    const overflowGroups = $derived(
        OVERFLOW_GROUPS.map((group) => ({
            ...group,
            presets: group.presets.filter(
                (preset) =>
                    offered.has(preset) &&
                    // Dropdown-only must not lose quick picks — segments are gone,
                    // so the dropdown carries the full offered universe.
                    (withoutQuickPicks ||
                        showQuickPicksInDropdown ||
                        !effectiveQuickPicks.includes(preset))
            ),
        })).filter((group) => group.presets.length > 0)
    );
    /**
     * Chips carry the full offered universe in enum order — no segments/overflow
     * split. `YearOnYear` is skipped for the same reason it is absent from
     * OVERFLOW_GROUPS: it resolves to the identical period as `Last12Months`.
     */
    const chipPresets = $derived(
        PRESET_SETS[preset].presets.filter((p) => p !== DatePeriodPreset.YearOnYear)
    );

    function select(preset: PeriodPreset): void {
        value = preset;
        onValueChange?.(preset);
    }
</script>

{#if variant === 'radio'}
    <ToggleGroup
        class={cn('flex flex-row flex-wrap gap-1.5', _class)}
        onValueChange={(v) => {
            // Re-clicking the active chip would deselect; a period is always set.
            if (v) select(v as PeriodPreset);
        }}
        type="single"
        {value}
        variant="default">
        <div class="space-y-1.5 space-x-1.5">
            {#each chipPresets as preset (preset)}
                <ToggleGroupItem
                    class="rounded-full! border border-input px-3 whitespace-normal shadow-xs"
                    value={preset}>
                    {labelOf(preset)}
                </ToggleGroupItem>
            {/each}
        </div>
    </ToggleGroup>
{:else}
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
{/if}
