<script lang="ts" module>
    import type { RestProps } from '@type/index';

    export type CurrencyInputProps = {
        /** Bindable primary — raw unmasked value e.g. 1000000 */
        value: string | number;
        /** Bindable optional — formatted display string e.g. '1.000.000' */
        maskedValue?: string;
        /** Left addon currency prefix, default 'Rp' */
        currency?: string;
        name?: string;
        disabled?: boolean;
        required?: boolean;
        /** Classes forwarded to the inner masked input — pass overrides to
            neutralize its default boxed `.input` styling. */
        inputClass?: string;
        class?: string;
    };
</script>

<script lang="ts">
    import { inputGroupClasses } from './input.svelte';
    import MaskedInput from './masked-input.svelte';

    import { cn } from '@utilities/shadcn';

    let {
        value = $bindable(''),
        maskedValue = $bindable(''),
        currency = 'Rp',
        name,
        disabled = false,
        required = false,
        inputClass,
        class: _class,
        ...props
    }: CurrencyInputProps & RestProps = $props();
</script>

<div
    class={cn(
        inputGroupClasses,
        'px-3 tabular-nums focus-within:bg-base-content/5 hover:bg-base-content/5',
        disabled && 'cursor-not-allowed opacity-50',
        _class
    )}>
    <span
        class="me-1 flex items-center self-stretch border-r border-input pe-3 text-sm font-medium whitespace-nowrap select-none">
        {currency}
    </span>
    <MaskedInput
        {name}
        class={inputClass}
        defaultClass="h-full w-full bg-transparent text-base outline-none md:text-sm"
        {disabled}
        maskPreset="currency"
        {required}
        bind:value
        bind:maskedValue
        {...props} />
</div>
