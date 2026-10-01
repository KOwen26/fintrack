<script lang="ts" module>
    import type { FlatpickrActionOpts } from '@lib/date-picker/flatpickr-action.svelte';

    type DateInputType =
        | 'date'
        | 'date-range'
        | 'date-time'
        | 'date-time-range'
        | 'month-year'
        | 'month-year-range'
        | 'year'
        | 'year-range';

    /** Exported so consumers can type the props they pass to DateInput. */
    export interface DateInputProps {
        type?: DateInputType;
        /** Bindable — canonical string value per type: date/date-range 'YYYY-MM-DD',
         *  date-time/date-time-range 'YYYY-MM-DDTHH:mm', month-year/month-year-range
         *  'YYYY-MM', year/year-range 'YYYY'. Ranges are joined with a comma —
         *  'start,end' (e.g. '2026-05-01,2026-05-31') Custom dateFormats containing
         *  a comma are unsupported for range types. */
        value: string;
        /** flatpickr options, the picker is re-created when these change (attachment reactivity). */
        options?: FlatpickrActionOpts;
        /** Months shown side by side for these types only date, date-range, date-time, date-time-range. */
        months?: 1 | 2;
        ref?: HTMLInputElement | null;
        id?: string;
        name?: string;
        placeholder?: string;
        required?: boolean;
        disabled?: boolean;
        'aria-invalid'?: boolean;
        class?: string;
        [key: string]: unknown;
    }
</script>

<script lang="ts">
    import type { FlatpickrInstance } from '@lib/date-picker/flatpickr-action.svelte';

    import { flatpickrAction } from '@lib/date-picker/flatpickr-action.svelte';
    import { timeRangePlugin, twoMonthHeaderPlugin } from '@lib/date-picker/flatpickr-plugins';

    import { cn } from '@utilities/shadcn';

    /** Default component options — apply to every variant. */
    const defaultOptions: FlatpickrActionOpts = {
        altInput: true,
    };

    /** Type registry — per-variant option overrides plus a placeholder pairing
     *  an action with a format example ('Pilih … ex. 01 Jan 2000'); the
     *  commented line under each keeps the previous variant for debugging. */
    const typeOptions: Record<
        DateInputType,
        { options: FlatpickrActionOpts; placeholder: string }
    > = {
        date: {
            options: { altFormat: 'd M Y', dateFormat: 'Y-m-d' },
            placeholder: 'Pilih tanggal cth. 01 Jan 2000',
            // placeholder: '08 Sep 2026',
        },
        'date-range': {
            options: { altFormat: 'd M Y', dateFormat: 'Y-m-d', mode: 'range' },
            placeholder: 'Pilih periode cth. 01 Jan 2000 - 01 Feb 2000',
            // placeholder: '08 Sep 2026 to 08 Oct 2026',
        },
        'date-time': {
            options: {
                altFormat: 'd M Y H:i',
                dateFormat: 'Y-m-d\\TH:i',
                enableTime: true,
                time_24hr: true,
            },
            placeholder: 'Pilih tanggal & waktu cth. 01 Jan 2000 09:30',
            // placeholder: '08 Sep 2026 14:30',
        },
        'date-time-range': {
            options: {
                altFormat: 'd M Y H:i',
                dateFormat: 'Y-m-d\\TH:i',
                enableTime: true,
                mode: 'range',
                time_24hr: true,
            },
            placeholder: 'Pilih periode cth. 01 Jan 2000 09:00 - 01 Feb 2000 17:00',
            // placeholder: '08 Sep 2026 09:00 to 08 Oct 2026 17:00',
        },
        'month-year': {
            options: { altFormat: 'M Y', dateFormat: 'Y-m', isMonthPicker: true },
            placeholder: 'Pilih bulan cth. Jan 2000',
            // placeholder: 'Sep 2026',
        },
        'month-year-range': {
            options: {
                altFormat: 'M Y',
                dateFormat: 'Y-m',
                isMonthPicker: true,
                mode: 'range',
            },
            placeholder: 'Pilih rentang bulan cth. Jan 2000 - Feb 2000',
            // placeholder: 'Sep 2026 to Oct 2026',
        },
        year: {
            options: { altFormat: 'Y', dateFormat: 'Y', isYearPicker: true },
            placeholder: 'Pilih tahun cth. 2000',
            // placeholder: '2026',
        },
        'year-range': {
            options: { altFormat: 'Y', dateFormat: 'Y', isYearPicker: true, mode: 'range' },
            placeholder: 'Pilih rentang tahun cth. 2000 - 2001',
            // placeholder: '2026 to 2027',
        },
    };

    let {
        type = 'date',
        value = $bindable(''),
        options,
        months = 1,
        ref = $bindable(null),
        id,
        name,
        placeholder: _placeholder = '',
        required = false,
        disabled = false,
        'aria-invalid': ariaInvalid = false,
        class: _class,
        ...rest
    }: DateInputProps = $props();

    /** Captured on ready so external value changes can be pushed into the picker.
     *  Raw state — never proxied, only tracked on reassignment (picker re-creation). */
    let picker = $state.raw<FlatpickrInstance | null>(null);

    const typeConfig = $derived(typeOptions[type]);
    const placeholder = $derived(_placeholder || typeConfig.placeholder);
    const allowInput = $derived(!!options?.allowInput);

    const inputClass = $derived(
        cn(
            'input flex min-h-12 w-full px-4 transition-colors',
            'focus-within:bg-base-content/5 focus-within:outline-none hover:bg-base-content/5',
            'pr-9',
            _class
        )
    );

    const resolvedOptions = $derived.by(() => {
        const { onChange: userOnChange, plugins: userPlugins, ...rest } = options ?? {};

        const twoMonths =
            months > 1 && !typeConfig.options.isMonthPicker && !typeConfig.options.isYearPicker;

        // range + time gets split Start/End time controls (the engine's single
        // shared panel edits whichever endpoint was touched last)
        const timeRange = !!typeConfig.options.enableTime && typeConfig.options.mode === 'range';

        return {
            // Classes for the visible alt-input clone flatpickr creates on mount
            // (the fork's fallback altInputClass is "form-control input" — not ours).
            altInputClass: inputClass,
            ariaDateFormat: 'd F Y',
            position: 'auto center',
            ...defaultOptions,
            ...typeConfig.options,
            ...(twoMonths ? { showMonths: months } : {}),
            ...rest,
            plugins: [
                ...(twoMonths ? [twoMonthHeaderPlugin()] : []),
                ...(timeRange ? [timeRangePlugin()] : []),
                ...(userPlugins ?? []),
            ],
            onChange: [
                () => {
                    // Keep the bound value in sync with the hidden ISO input,
                    // normalizing the engine's locale separator to the
                    // canonical comma contract (see the value docblock).
                    if (picker) {
                        value = picker.input.value?.split(picker.l10n.rangeSeparator).join(',');
                    }
                },
                ...(userOnChange ? [userOnChange].flat() : []),
            ],
            onReady: (instance: FlatpickrInstance) => (picker = instance),
        };
    });

    // flatpickr writes the display string to the visible alt-input clone and the
    // ISO string to the real input. External value changes (form reset, server
    // prefill) are pushed back through setDate without re-triggering onChange —
    // re-expanding the canonical comma into the instance's own separator so the
    // engine can parse the range.
    $effect(() => {
        if (picker) {
            // const raw = value?.split(',').join(picker.l10n.rangeSeparator);
            const raw = value;

            if (picker.input.value !== raw) {
                picker.setDate(raw, false);
            }
        }
    });

    // The alt-input is a plain DOM clone created outside Svelte — dynamic props must be mirrored onto it
    $effect(() => {
        const alt = picker?.altInput;

        if (!alt) {
            return;
        }

        alt.className = inputClass;
        alt.disabled = disabled;
        alt.placeholder = placeholder;
        alt.readOnly = !allowInput;
        alt.required = required;

        if (ariaInvalid) {
            alt.setAttribute('aria-invalid', 'true');
        } else {
            alt.removeAttribute('aria-invalid');
        }

        // Move the label target to the visible clone so label clicks open the picker.
        if (id) {
            alt.id = id;
            picker?.input.removeAttribute('id');
        }
    });
</script>

<div class="relative">
    <!-- Value is handled by side-effect (onChange, etc) for easier ranges control -->
    <input
        bind:this={ref}
        {id}
        {name}
        {...rest}
        class={inputClass}
        {@attach flatpickrAction(resolvedOptions)}
        aria-invalid={ariaInvalid}
        {disabled}
        {placeholder}
        {required}
        type="text" />
    <i
        class="pointer-events-none absolute top-1/2 right-4 iconify size-4 -translate-y-1/2 text-muted-foreground solar--calendar-line-duotone"
        aria-hidden="true">
    </i>
</div>
