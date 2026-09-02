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
        class?: string;
    };
</script>

<script lang="ts">
    import MaskedInput from './masked-input.svelte';

    import { cn } from '@utilities/shadcn';

    let {
        value = $bindable(''),
        maskedValue = $bindable(''),
        currency = 'Rp',
        name,
        disabled = false,
        required = false,
        class: className,
        ...props
    }: CurrencyInputProps & RestProps = $props();
</script>

<div
    class={cn(
        'input w-full overflow-clip tabular-nums',
        disabled && 'cursor-not-allowed opacity-50',
        className
    )}>
    <span
        class="me-1 flex items-center border-r border-(--input-color) pe-2 text-sm
               font-medium whitespace-nowrap text-base-content/70 select-none">
        {currency}
    </span>
    <MaskedInput
        {name}
        defaultClass=""
        {disabled}
        maskPreset="currency"
        {required}
        bind:value
        bind:maskedValue
        {...props} />
</div>
