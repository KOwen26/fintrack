import type { Instance } from 'flatpickr_plus/dist/types/instance';
import type { Hook, Options, Plugin } from 'flatpickr_plus/dist/types/options';

import { yearSelectPlugin } from '@lib/date-picker/flatpickr-plugins';
import { addYears } from 'date-fns';
import baseFlatpickr from 'flatpickr_plus';
import { Indonesian } from 'flatpickr_plus/dist/l10n/id';
import monthSelectPlugin from 'flatpickr_plus/dist/plugins/monthSelect';
import yearDropdownPlugin from 'flatpickr_plus/dist/plugins/yearDropdown';

import 'flatpickr_plus/dist/flatpickr.css';
import 'flatpickr_plus/dist/plugins/monthSelect/style.css';

export type FlatpickrInstance = Instance;

/** Wrapper-only flags on top of the engine's options. */
export interface AdditionalOptions {
    /** Whether the calendar is a month picker. */
    isMonthPicker?: boolean;
    /** Whether the calendar is a year picker. */
    isYearPicker?: boolean;
    /** Shorthand month names in the month/year picker plugins. */
    shorthand?: boolean;
}

/**
 * Everything the wrapper accepts — engine options plus the wrapper flags.
 * Replaces svelte-flatpickr-plus's FlatpickrOptions: its `plugins` key resolves
 * to the DOM's Plugin interface (unresolved name in their d.ts), structurally
 * poisoning any comparison against the engine's own Options.
 */
export type FlatpickrOptions = Options & AdditionalOptions;

/** A flatpickr plugin — receives the instance, returns hook/config contributions.
 *  Engine-compatible with flatpickr_plus's `Plugin` (verified identical to
 *  vanilla flatpickr's), authored ergonomically against our options type. */
export type FlatpickrPlugin = (fp: FlatpickrInstance) => Partial<FlatpickrOptions>;

export type FlatpickrActionOpts = Omit<FlatpickrOptions, 'onReady'> & {
    /** Fires once the calendar instance is ready — use it to capture the instance. */
    onReady?: (instance: FlatpickrInstance) => void;
    /** Extra plugins — merged ahead of the built-ins, so their hooks execute after them. */
    plugins?: Plugin[];
};

/**
 * House defaults for every flatpickr instance:
 *
 * - Explicit min/max range defeats the year-dropdown plugin's implicit clamp. Both bounds are honored by every mode — day grids, month grid, and the year grid.
 * - disableMobile keeps the flatpickr UI (and our theming) on every device.
 * - time_24hr matches the app's ISO 'YYYY-MM-DDTHH:mm' output.
 * - autoFillDefaultTime off — a back-office form must not mutate values just because the field was focused.
 */
const defaults: FlatpickrOptions = {
    allowInput: false,
    autoFillDefaultTime: false,
    disableMobile: true,
    locale: Indonesian,
    minDate: '1900-01-01',
    maxDate: addYears(new Date(), 5),
    /** Fork parity: reset prevention applies when defaultDate is set — see resetFlatpickr. */
    resetToDefault: true,
    time_24hr: true,
};

const toHooks = (hook?: Hook | Hook[]): Hook[] => (hook ? [hook].flat() : []);

/** The fork's reset semantics: clear on native reset; when a defaultDate is
 *  set and resetToDefault is on, also cancel the native value restoration
 *  (the widget re-defaults itself instead). */
function resetFlatpickr(event: Event, fp: FlatpickrInstance, opts: FlatpickrOptions): void {
    fp.clear();

    if (opts.defaultDate && opts.resetToDefault) {
        event.preventDefault();
    }
}

/**
 * Creates a flatpickr instance with the house defaults, built-in plugins
 * (monthSelect for month-year mode; the year-select grid replaces the year
 * dropdown in year mode), and the wrapper's merged hooks. Framework-agnostic
 * — the only side effects are on the node itself and the returned instance.
 *
 * Vendored & rewritten from svelte-flatpickr-plus@2.1.2's `createFlatpickr`
 * (dist/actions.svelte.js), which hardcodes the plugins array (options.plugins
 * are discarded) and ships a debug console.log in year-picker mode. To port
 * upstream changes, diff against that file while the package remains
 * installed for types.
 *
 * Plugin mechanics: the engine PREPENDS each plugin's hooks to the config
 * arrays while iterating plugins in array order — so hook EXECUTION runs in
 * reverse array order (last in the array fires first; user hooks always run
 * last). Extras are therefore placed FIRST in the array so they EXECUTE after
 * the built-ins (DOM they appended, config they defaulted).
 */
export function createFlatpickr(
    node: HTMLInputElement,
    opts: FlatpickrActionOpts = {}
): FlatpickrInstance {
    const { isMonthPicker, isYearPicker, onReady, plugins, ...rest } = opts;
    const merged: FlatpickrOptions = { ...defaults, ...rest };
    const basePlugins: Plugin[] = [];

    if (isMonthPicker) {
        if (!merged.dateFormat) {
            merged.altFormat = 'F Y';
            merged.ariaDateFormat = 'F Y';
            merged.dateFormat = 'F Y';
        }

        basePlugins.push(
            monthSelectPlugin({
                altFormat: merged.altFormat,
                dateFormat: merged.dateFormat,
                shorthand: merged.shorthand,
            })
        );
    }

    // year mode swaps the year dropdown for the 5×5 year-select grid
    if (isYearPicker) {
        basePlugins.push(yearSelectPlugin());
    }

    // Parity with the original svelte-flatpickr-plus wrapper: every calendar
    // picker gets the year dropdown — the plugin hides the engine's raw year
    // numInput and appends a themed <select> to the header (year mode is the
    // exception above; its grid replaces the whole popup including this).
    if (!merged.noCalendar && !isYearPicker) {
        basePlugins.push(yearDropdownPlugin());
    }

    node.setAttribute('autocomplete', 'off');

    if (!merged.allowInput) {
        node.setAttribute('readonly', 'true');
    }

    return baseFlatpickr(node, {
        ...merged,
        onClose: [
            (_dates: Date[], _dateStr: string, self: FlatpickrInstance) => {
                (self.altInput ?? self.input).blur();
            },
            ...toHooks(merged.onClose),
        ],
        onOpen: [
            (_dates: Date[], _dateStr: string, self: FlatpickrInstance) => {
                const day =
                    self.days?.querySelector<HTMLElement>('.today') ??
                    self.days?.querySelector<HTMLElement>('.selected');

                (day ?? self.hourElement)?.focus();
            },
            ...toHooks(merged.onOpen),
        ],
        onReady: [
            (_dates: Date[], _dateStr: string, self: FlatpickrInstance) => {
                onReady?.(self);
            },
            ...toHooks(merged.onReady),
        ],
        // opts.plugins first in the array ⇒ their hooks execute AFTER the built-ins
        plugins: [...(plugins ?? []), ...basePlugins],
    });
}

/**
 * `{@attach flatpickrAction(opts)}` re-runs (picker destroyed and recreated)
 * whenever the options object changes — attachments are fully reactive.
 */
export function flatpickrAction(opts: FlatpickrActionOpts = {}) {
    return (node: HTMLInputElement) => {
        const instance = createFlatpickr(node, opts);

        // In wrap mode (see resetFlatpickr) the input lives inside custom markup, so the form is resolved from the wrapped input.
        // One correction kept from the rewrite: stored references make the removal actually work.
        // resetFlatpickr reads exactly these two options — build only those, so the custom onReady signature never leaks into FlatpickrOptions.
        const resetOpts: FlatpickrOptions = {
            defaultDate: opts.defaultDate,
            resetToDefault: opts.resetToDefault ?? defaults.resetToDefault,
        };
        const resetForm = opts.wrap
            ? node.querySelector<HTMLInputElement>('input')?.form
            : node.form;
        const resetHandler = (event: Event) => resetFlatpickr(event, instance, resetOpts);

        resetForm?.addEventListener('reset', resetHandler);

        return () => {
            resetForm?.removeEventListener('reset', resetHandler);
            instance.destroy();
        };
    };
}
