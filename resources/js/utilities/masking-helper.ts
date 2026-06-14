import type { MaskedNumberOptions, MaskedPatternOptions } from 'imask';

import IMask from 'imask';

export default class MaskingHelper {
    private static readonly currency: MaskedNumberOptions = {
        mask: Number,
        mapToRadix: [','],
        min: 0,
        max: 99_999_999_999_999,
        normalizeZeros: true,
        overwrite: 'shift',
        padFractionalZeros: true,
        radix: ',',
        scale: 0,
        thousandsSeparator: '.',
    };

    private static readonly phone_number: MaskedPatternOptions = {
        mask: '000-000-000[-00000]',
    };

    public static readonly input_mask_preset = {
        currency: this.currency,
        phone_number: this.phone_number,
    };

    public static formatToInputMask(value: string | number, maskOptions): string {
        if (typeof value !== 'string') value = String(value);
        const masked = IMask.pipe(
            value,
            maskOptions,
            IMask.PIPE_TYPE.UNMASKED,
            IMask.PIPE_TYPE.MASKED
        );

        return masked ?? value;
    }

    public static formatFromInputMask(value: string, maskOptions): string {
        return IMask.pipe(value, maskOptions, IMask.PIPE_TYPE.MASKED, IMask.PIPE_TYPE.UNMASKED);
    }

    public static formatToMaskPreset(value: string | number, preset: InputMaskPresetKey): string {
        return this.formatToInputMask(value, this.input_mask_preset[preset]);
    }

    public static formatToDataMask(
        value: string | number,
        options: Partial<{
            preset: 'email' | 'mask-all' | 'phone';
            skip_length: number;
            mask_length: number;
            mask_value: string;
        }> = {}
    ): string {
        value = String(value);
        if (!value?.length) return value;

        const { preset = null, skip_length = 1, mask_length = 3, mask_value = '*' } = options;

        const whitelist_presets = {
            default: ['-', '_', '.'],
            email: ['@', '.'],
            phone: ['+', '(', ')', '-'],
            'mask-all': [],
        };
        const whitelist = whitelist_presets[preset] || whitelist_presets.default;

        let words = value.split(preset === 'email' ? '@' : ' ');
        if (preset === 'email') {
            words = words.flatMap((element, index) => {
                return index < words.length - 1 ? [element, '@'] : [element];
            });
        }

        const maskedValue = words
            .map((word) => {
                let index = 0;

                return word
                    .split('')
                    .map((char) => {
                        if (whitelist.includes(char)) return char;
                        const cycle_length = skip_length + mask_length;
                        const cycle_position = index % cycle_length;
                        index++;

                        return cycle_position >= skip_length && cycle_position < cycle_length
                            ? mask_value
                            : char;
                    })
                    .join('');
            })
            .join(preset === 'email' ? '' : ' ');

        return maskedValue;
    }
}

export type InputMaskPresetKey = keyof typeof MaskingHelper.input_mask_preset;
