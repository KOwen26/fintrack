import MaskingHelper from './masking-helper';

export default class Formatter {
    public static currency(value: number | string) {
        return 'Rp ' + MaskingHelper.formatToMaskPreset(value, 'currency');
    }
}
