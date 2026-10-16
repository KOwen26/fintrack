import type { Day, LocaleWidth } from 'date-fns';

import { parse, startOfDay } from 'date-fns';
import { enUS } from 'date-fns/locale';

export default class DateTimeHelper {
    /**
     * Local-midnight Date from a `Y-m-d` string — zone-free by construction.
     *
     * The wire carries calendar days, but consumers needing native Dates (and
     * any getDay/getDate math) must pin "midnight" to the *viewer's* midnight:
     * date-only strings parse as UTC and offset-carrying strings convert, both
     * shifting the date for browsers west of the serialization zone.
     * `startOfDay` is not optional — bare `parse` inherits the reference
     * date's time-of-day.
     */
    static parseLocalDay(day: string): Date {
        return startOfDay(parse(day, 'yyyy-MM-dd', new Date()));
    }

    static weekdayNameByIndex(
        index: number,
        variant: 'short' | 'long' = 'short',
        weekStart: 'monday' | 'sunday' = 'monday'
    ): string {
        return DateTimeHelper.weekdayNames(variant, weekStart)[index];
    }

    /**
     * Weekday names Monday-first, indexed by ISO weekday (1–7 → [0]–[6]).
     * Reads the bundled locale data directly rather than formatting a
     * sample date — no anchor, and deterministic where runtime ICU could
     * silently fall back to English. date-fns days run Sunday-first
     * (0 = Sunday), so ISO 7 wraps to 0.
     *
     * @example DateTimeHelper.weekdayNames('short') // ["Sen", "Sel", … "Min"]
     */
    static weekdayNames(
        variant: 'short' | 'long' = 'short',
        weekStart: 'monday' | 'sunday' = 'monday'
    ): string[] {
        const firstDay = weekStart === 'monday' ? 1 : 0;
        const variantMap: Record<'short' | 'long', LocaleWidth> = {
            short: 'abbreviated',
            long: 'wide',
        };

        return Array.from({ length: 7 }, (_, i) => {
            const weekday = ((firstDay + i) % 7) as Day;

            return enUS.localize.day(weekday, {
                width: variantMap[variant],
            });
        });
    }

    /**
     * @param value - Date, ISO string, or timestamp
     * @param preset - Format preset:
     *
     * | preset | Example output |
     * |---|---|
     * | `datetime` | `01 Jan 2025 14:30` |
     * | `datetime-long` | `1 Januari 2025 14.30` |
     * | `date` | `1 Januari 2025` |
     * | `month-year` | `Januari 2025` |
     * | `month-year-short` | `Mei 26` |
     * | `time` | `14.30` |
     * | `day-date` | `Sen, 3 Feb 26` |
     * | `day-date-long` | `Senin, 3 Februari 26` |
     *
     * @example DateTimeHelper.format('2025-01-01T14:30:00', 'date') // "1 Januari 2025"
     */
    static format(
        value: Date | string | number,
        preset:
            | 'datetime'
            | 'datetime-long'
            | 'date'
            | 'month-year'
            | 'month-year-short'
            | 'time'
            | 'day-date'
            | 'day-date-long' = 'datetime'
    ): string {
        if (!value) return '';

        const date = new Date(value);

        if (preset === 'datetime') {
            const parts = new Intl.DateTimeFormat('id-ID', {
                year: 'numeric',
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            }).formatToParts(date);

            const p = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

            return `${p.day} ${p.month} ${p.year} ${p.hour}:${p.minute}`;
        }

        if (preset === 'day-date' || preset === 'day-date-long') {
            const isLong = preset === 'day-date-long';
            const parts = new Intl.DateTimeFormat('id-ID', {
                weekday: isLong ? 'long' : 'short',
                day: 'numeric',
                month: isLong ? 'long' : 'short',
                year: '2-digit',
            }).formatToParts(date);

            const p = Object.fromEntries(parts.map(({ type, value }) => [type, value]));

            return `${p.weekday}, ${p.day} ${p.month} ${p.year}`;
        }

        let dateOptions: Intl.DateTimeFormatOptions = {};

        switch (preset) {
            case 'datetime-long':
                dateOptions = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                };
                break;
            case 'date':
                dateOptions = {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                };
                break;
            case 'month-year':
                dateOptions = {
                    year: 'numeric',
                    month: 'long',
                };
                break;
            case 'month-year-short':
                dateOptions = {
                    year: '2-digit',
                    month: 'short',
                };
                break;
            case 'time':
                dateOptions = {
                    hour: 'numeric',
                    minute: 'numeric',
                };
                break;
            default:
                dateOptions = {};
                break;
        }

        return date.toLocaleString('id-ID', dateOptions);
    }
}
