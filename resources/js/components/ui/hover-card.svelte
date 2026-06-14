<script lang="ts" module>
    import { LinkPreview as HoverCardPrimitive } from 'bits-ui';

    const baseContentClass =
        'bg-popover text-popover-foreground data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2 z-50 mt-3 w-64 rounded-md border p-4 shadow-md outline-hidden outline-none';

    export type HoverCardProps = {
        triggerRef?: HTMLElement | null;
        contentRef?: HTMLElement | null;
        open?: boolean;
        trigger: Snippet<[{ props: HoverCardPrimitive.TriggerProps }]>;
        children?: Snippet;
        rootProps?: Partial<HoverCardPrimitive.RootProps>;
        portalProps?: HoverCardPrimitive.PortalProps;
    } & HoverCardPrimitive.ContentProps &
        HTMLAttributes<HTMLDivElement>;
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    let {
        triggerRef = $bindable(null),
        contentRef = $bindable(null),
        open = $bindable(false),
        class: className,
        align = 'center',
        sideOffset = 4,
        trigger,
        children,
        rootProps,
        portalProps,
        ...restProps
    }: HoverCardProps = $props();

    const contentClass = $derived(cn(baseContentClass, className));
</script>

<HoverCardPrimitive.Root bind:open {...rootProps}>
    <HoverCardPrimitive.Trigger
        child={typeof trigger === 'function' && trigger}
        data-slot="hover-card-trigger"
        bind:ref={triggerRef}>
        {trigger}
    </HoverCardPrimitive.Trigger>

    <HoverCardPrimitive.Portal {...portalProps}>
        <HoverCardPrimitive.Content
            class={contentClass}
            {align}
            data-slot="hover-card-content"
            {sideOffset}
            bind:ref={contentRef}
            {...restProps}>
            {@render children?.()}
        </HoverCardPrimitive.Content>
    </HoverCardPrimitive.Portal>
</HoverCardPrimitive.Root>
