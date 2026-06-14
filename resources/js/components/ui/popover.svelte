<script lang="ts" module>
    import { Popover as PopoverPrimitive } from 'bits-ui';

    const baseContentClass =
        'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 w-60 origin-(--bits-popover-content-transform-origin) rounded-md border p-4 shadow-md outline-hidden';

    export type PopoverProps = {
        triggerRef?: HTMLElement | null;
        contentRef?: HTMLElement | null;
        trigger: Snippet;
        // trigger: Snippet<[{ props: PopoverPrimitive.TriggerProps }]>;
        children?: Snippet;
        rootProps?: Partial<PopoverPrimitive.RootProps>;
        portalProps?: PopoverPrimitive.PortalProps;
        triggerClass?: string;
    } & PopoverPrimitive.ContentProps &
        HTMLAttributes<HTMLDivElement> &
        Pick<PopoverPrimitive.RootProps, 'open'>;
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        triggerRef = $bindable(null),
        contentRef = $bindable(null),
        open = $bindable(null),
        rootProps,
        class: className,
        triggerClass = '',
        sideOffset = 6,
        align = 'center',
        portalProps,
        trigger,
        children,
        ...restProps
    }: PopoverProps = $props();

    const contentClass = $derived(cn(baseContentClass, className));
</script>

<PopoverPrimitive.Root {...rootProps} bind:open>
    <PopoverPrimitive.Trigger
        class={triggerClass}
        data-slot="popover-trigger"
        bind:ref={triggerRef}>
        {@render trigger?.()}
    </PopoverPrimitive.Trigger>
    <PopoverPrimitive.Portal {...portalProps}>
        <PopoverPrimitive.Content
            class={contentClass}
            {align}
            data-slot="popover-content"
            {sideOffset}
            bind:ref={contentRef}
            {...restProps}>
            {@render children?.()}
        </PopoverPrimitive.Content>
    </PopoverPrimitive.Portal>
</PopoverPrimitive.Root>
