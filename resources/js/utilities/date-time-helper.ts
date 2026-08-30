export default class DateTimeHelper {
    /**
     * @param value - Date, ISO string, or timestamp
     * @param preset - Format preset:
     *
     * | preset | Example output |
     * |---|---|
     * | `datetime` | `01 Jan 2025 14:30` |
     * | `datetime-long` | `1 Januari 2025 14.30` |
     * | `date` | `1 Januari 2025` |
     * | `time` | `14.30` |
     *
     * @example DateTimeHelper.format('2025-01-01T14:30:00', 'date') // "1 Januari 2025"
     */
    static format(
        value: Date | string | number,
        preset: 'datetime' | 'datetime-long' | 'date' | 'time' = 'datetime'
    ): string {
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
