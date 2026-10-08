<script lang="ts">
    import type { Snippet } from 'svelte';

    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

    interface Props {
        value: number | string | null | undefined;
        symbol?: string;
        withoutSymbol?: boolean;
        class?: string;
        symbolClass?: string;
        amountClass?: string;
        operator?: Snippet;
    }

    let {
        value,
        operator,
        symbol = 'Rp',
        withoutSymbol = false,
        class: _class,
        symbolClass,
        amountClass,
    }: Props = $props();

    const isMinus = $derived(Number(value) < 0);
    const amount = $derived(Formatter.currency(value ?? 0));
</script>

<span
    class={cn(
        // '@container/currency-amount',
        'inline-flex items-baseline gap-0.5 whitespace-nowrap',
        _class
    )}>
    {#if operator}
        {@render operator?.()}
    {:else if isMinus}
        <i
            class={[
                'iconify size-3 self-center text-current/70 @xs/currency-amount:size-5',
                isMinus && 'solar--minus-linear',
            ]}></i>
    {/if}

    {#if !withoutSymbol}
        <span class={cn('text-[clamp(12px,0.70em,1em)] leading-none text-current/70', symbolClass)}>
            {symbol}
        </span>
    {/if}

    <span class={cn('tabular-nums', amountClass)}>
        {amount}
    </span>
</span>
