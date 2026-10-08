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
}
