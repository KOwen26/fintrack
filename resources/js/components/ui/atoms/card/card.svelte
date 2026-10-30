<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        size = 'md',
        /** `flat` drops every paint utility (bg, ring, rounding, clipping) — layout and spacing stay. */
        variant = 'default',
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> & {
        size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
        variant?: 'default' | 'flat';
    } = $props();
</script>

<div
    bind:this={ref}
    data-slot="card"
    class={cn(
        'group/card @container/card',
        '[--card-spacing:--spacing(5)] data-[size=lg]:[--card-spacing:--spacing(6)] data-[size=md]:[--card-spacing:--spacing(5)] data-[size=sm]:[--card-spacing:--spacing(4)] data-[size=xl]:[--card-spacing:--spacing(8)] data-[size=xs]:[--card-spacing:--spacing(3)]',
        'flex flex-col gap-(--card-spacing) overflow-hidden rounded-xl bg-card py-(--card-spacing) text-sm text-card-foreground ring-1 ring-foreground/10  has-data-[slot=card-footer]:pb-0 has-[>img:first-child]:pt-0 *:[img:first-child]:rounded-t-xl *:[img:last-child]:rounded-b-xl',
        variant === 'flat' &&
            'overflow-visible rounded-none bg-transparent text-current ring-0 *:[img:first-child]:rounded-none *:[img:last-child]:rounded-none',
        className
    )}
    data-size={size}
    data-variant={variant}
    {...restProps}>
    {@render children?.()}
</div>
