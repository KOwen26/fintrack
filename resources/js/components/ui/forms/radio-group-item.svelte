<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type {
        RadioGroupItemProps as BitsRadioGroupItemProps,
        RadioGroupItemPropsWithoutHTML,
    } from 'bits-ui';
    import type { Snippet } from 'svelte';

    export type RadioGroupItemProps = RadioGroupItemPropsWithoutHTML & {
        children?: Snippet;
    };
</script>

<script lang="ts">
    import type { WithoutChildren } from '@utilities/shadcn.js';

    import { RadioGroup as RadioGroupPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithoutChildren<RadioGroupItemProps & BitsRadioGroupItemProps> & RestProps = $props();
</script>

<label class="flex w-fit items-center gap-2">
    <RadioGroupPrimitive.Item
        data-slot="radio-group-item"
        class={cn(
            'border-input text-primary focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 aspect-square size-4 shrink-0 rounded-full border shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
            className
        )}
        bind:ref
        {...restProps}>
        {#snippet children({ checked })}
            <div
                data-slot="radio-group-indicator"
                class="relative flex items-center justify-center">
                {#if checked}
                    <div class="size-2 rounded-full bg-current"></div>
                {/if}
            </div>
        {/snippet}
    </RadioGroupPrimitive.Item>
    {@render children?.()}
</label>
