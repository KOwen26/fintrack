<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { InputMaskPresetKey } from '@utilities/masking-helper';

    export interface MaskedInputProps {
        value: string | number;
        maskedValue?: string;
        mask?: any;
        maskPreset?: InputMaskPresetKey;
        defaultClass?: string;
    }
</script>

<script lang="ts">
    import IMask, { InputMask } from 'imask';
    import { onDestroy, onMount } from 'svelte';

    import MaskingHelper from '@utilities/masking-helper';

    let {
        value = $bindable(),
        maskedValue = $bindable(''),
        mask,
        maskPreset: _maskPreset = undefined,
        defaultClass = 'input',
        name,
        ...props
    }: MaskedInputProps & RestProps = $props();

    if (!mask && !_maskPreset) {
        throw new Error('MaskedInput: mask or maskPreset is required');
    }

    mask = mask ?? MaskingHelper?.input_mask_preset[_maskPreset];

    let input: HTMLInputElement;
    let maskRef: InputMask | undefined;

    function getValue() {
        return maskRef?.typedValue;
    }

    function setValue(v: any) {
        if (maskRef) {
            v = v == null ? '' : v;
            maskRef.typedValue = v;
        }
    }

    function writeValue(v: any) {
        if (
            getValue() !== v ||
            // handle cases like Number('') === 0,
            // for details see https://github.com/uNmAnNeR/imaskjs/issues/134
            (typeof v !== 'string' && value === '' && !maskRef?.el.isActive)
        ) {
            setValue(v);
        }
    }

    function accept() {
        value = getValue();
        maskedValue = maskRef.value;
    }

    onMount(() => {
        maskRef = IMask(input, mask).on('accept', accept);
        setValue(value);
    });

    onDestroy(() => {
        if (maskRef) maskRef.destroy();
        maskRef = undefined;
    });

    $effect.pre(() => {
        writeValue(value);
    });
</script>

<input
    bind:this={input}
    name={`${name}_mask`}
    class={[defaultClass, props.class]}
    type="text"
    {...props} />

<input {name} type="hidden" bind:value />
