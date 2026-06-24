<script lang="ts" module>
    import { Tooltip as TooltipPrimitive } from 'bits-ui';

    const baseContentClass =
        'bg-primary text-primary-foreground animate-in fade-in-0 zoom-in-95 data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-fit origin-(--bits-tooltip-content-transform-origin) rounded-md px-3 py-1.5 text-xs text-balance';

    const arrowBaseClass = 'bg-primary z-50 size-2.5 rotate-45 rounded-[2px]';
    const arrowPositionClass = twMerge([
        'data-[side=top]:translate-x-1/2 data-[side=top]:translate-y-[calc(-50%_+_2px)]',
        'data-[side=bottom]:-translate-x-1/2 data-[side=bottom]:-translate-y-[calc(-50%_+_1px)]',
        'data-[side=right]:translate-x-[calc(50%_+_2px)] data-[side=right]:translate-y-1/2',
        'data-[side=left]:-translate-y-[calc(50%_-_3px)]',
    ]);

    export type TooltipProps = {
        triggerRef?: HTMLElement | null;
        contentRef?: HTMLElement | null;
        arrowClasses?: string;
        trigger: Snippet<[{ props: TooltipPrimitive.TriggerProps }]>;
        children?: Snippet;
        rootProps?: Partial<TooltipPrimitive.RootProps>;
    } & TooltipPrimitive.ContentProps &
        HTMLAttributes<HTMLDivElement> &
        Pick<TooltipPrimitive.RootProps, 'open'>;
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { twMerge } from 'tailwind-merge';

    import { cn } from '@utilities/shadcn.js';

    let {
        triggerRef = $bindable(null),
        contentRef = $bindable(null),
        open = $bindable(null),
        rootProps,
        class: className,
        sideOffset = 0,
        side = 'top',
        arrowClasses,
        trigger,
        children,
        ...restProps
    }: TooltipProps = $props();

    const contentClass = $derived(cn(baseContentClass, className));
    const arrowClass = $derived(cn(arrowBaseClass, arrowPositionClass, arrowClasses));
</script>

<TooltipPrimitive.Root {...rootProps} bind:open>
    <TooltipPrimitive.Trigger data-slot="tooltip-trigger" bind:ref={triggerRef}>
        {#snippet child({ props })}
            {@render trigger?.({ props })}
        {/snippet}
    </TooltipPrimitive.Trigger>
    <TooltipPrimitive.Portal>
        <TooltipPrimitive.Content
            data-slot="tooltip-content"
            class={contentClass}
            {side}
            {sideOffset}
            bind:ref={contentRef}
            {...restProps}>
            {@render children?.()}
            <TooltipPrimitive.Arrow>
                {#snippet child({ props })}
                    <div class={arrowClass} {...props}></div>
                {/snippet}
            </TooltipPrimitive.Arrow>
        </TooltipPrimitive.Content>
    </TooltipPrimitive.Portal>
</TooltipPrimitive.Root>
