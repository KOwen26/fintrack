import MaskingHelper from '@lib/masking-handler';

export default class Formatter {
    public static currency(value: number | string, withSymbol: boolean | string = false): string {
        const formatted = MaskingHelper.formatToMaskPreset(value, 'currency');

        if (withSymbol === false || withSymbol === '') {
            return formatted;
        }

        const symbol = withSymbol === true ? 'Rp' : withSymbol;

        return symbol + formatted;
    }

    /**
     * Compact amount notation for tight spaces (chart cells, stat chips):
     * 333_500 → "333.5k", 2_000_000 → "2M", 450 → "450". Signed values keep
     * their sign; no currency symbol or grouping separators.
     */
    public static compactAmount(value: number): string {
        const abs = Math.abs(value);

        if (abs >= 1_000_000_000_000) {
            return `${Math.round((value / 1_000_000_000_000) * 10) / 10}T`;
        }

        if (abs >= 1_000_000_000) {
            return `${Math.round((value / 1_000_000_000) * 10) / 10}B`;
        }

        if (abs >= 1_000_000) {
            return `${Math.round((value / 1_000_000) * 10) / 10}M`;
        }

        if (abs >= 1_000) {
            return `${Math.round((value / 1_000) * 10) / 10}k`;
        }

        return String(value);
    }
}
