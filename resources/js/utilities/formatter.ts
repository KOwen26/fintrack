import MaskingHelper from '@lib/masking-handler';

export default class Formatter {
    public static currency(value: number | string, withoutLabel: boolean = false) {
        const formatted = MaskingHelper.formatToMaskPreset(value, 'currency');

        return withoutLabel ? formatted : 'Rp' + formatted;
    }
}
