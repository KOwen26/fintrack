<script lang="ts">
    import DrawerOverlay from './drawer-overlay.svelte';

    import { Drawer as DrawerPrimitive } from 'vaul-svelte';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        portalProps,
        /** Set false to omit the dimming overlay, e.g. keep context visible behind the sheet. */
        overlay = true,
        children,
        ...restProps
    }: DrawerPrimitive.ContentProps & {
        portalProps?: DrawerPrimitive.PortalProps;
        overlay?: boolean;
    } = $props();
</script>

<DrawerPrimitive.Portal {...portalProps}>
    {#if overlay}
        <DrawerOverlay />
    {/if}
    <DrawerPrimitive.Content
        data-slot="drawer-content"
        class={cn(
            'group/drawer-content fixed z-50 flex h-auto flex-col bg-background',
            // Direction top
            'data-[vaul-drawer-direction=top]:inset-x-0 data-[vaul-drawer-direction=top]:top-0 data-[vaul-drawer-direction=top]:mb-24 data-[vaul-drawer-direction=top]:max-h-[80vh] data-[vaul-drawer-direction=top]:rounded-b-2xl data-[vaul-drawer-direction=top]:border-b',
            // Direction bottom
            'data-[vaul-drawer-direction=bottom]:inset-x-0 data-[vaul-drawer-direction=bottom]:bottom-0 data-[vaul-drawer-direction=bottom]:mt-24 data-[vaul-drawer-direction=bottom]:max-h-[80vh] data-[vaul-drawer-direction=bottom]:rounded-t-2xl data-[vaul-drawer-direction=bottom]:border-t', //
            // Direction right
            'data-[vaul-drawer-direction=right]:inset-y-0 data-[vaul-drawer-direction=right]:right-0 data-[vaul-drawer-direction=right]:w-3/4 data-[vaul-drawer-direction=right]:border-l data-[vaul-drawer-direction=right]:sm:max-w-sm',
            // Direction left
            'data-[vaul-drawer-direction=left]:inset-y-0 data-[vaul-drawer-direction=left]:left-0 data-[vaul-drawer-direction=left]:w-3/4 data-[vaul-drawer-direction=left]:border-r data-[vaul-drawer-direction=left]:sm:max-w-sm',
            className
        )}
        bind:ref
        {...restProps}>
        <div
            class="mx-auto mt-4 hidden h-2 w-25 shrink-0 rounded-full bg-muted group-data-[vaul-drawer-direction=bottom]/drawer-content:block">
        </div>
        {@render children?.()}
    </DrawerPrimitive.Content>
</DrawerPrimitive.Portal>
