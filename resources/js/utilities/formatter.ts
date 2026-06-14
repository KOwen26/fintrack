import MaskingHelper from './masking-helper';

export default class Formatter {
    public static currency(value: number | string) {
        return MaskingHelper.formatToMaskPreset(value, 'currency');
    }
}
