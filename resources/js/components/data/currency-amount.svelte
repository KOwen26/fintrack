<script lang="ts">
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props {
        value: number | string;
        symbol?: string;
        withoutSymbol?: boolean;
        class?: string;
        symbolClass?: string;
        amountClass?: string;
    }

    let {
        value,
        symbol = 'Rp',
        withoutSymbol = false,
        class: _class,
        symbolClass,
        amountClass,
    }: Props = $props();

    const amount = $derived(Formatter.currency(value));
</script>

<span class={cn('inline-flex items-baseline gap-0.5 whitespace-nowrap', _class)}>
    {#if !withoutSymbol}
        <span class={cn('text-[clamp(12px,0.70em,1em)] leading-none text-current/70', symbolClass)}>
            {symbol}
        </span>
    {/if}

    <span class={cn('tabular-nums', amountClass)}>
        {amount}
    </span>
</span>
