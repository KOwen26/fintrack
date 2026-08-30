<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import {
        SIDEBAR_COOKIE_MAX_AGE,
        SIDEBAR_COOKIE_NAME,
        SIDEBAR_WIDTH,
        SIDEBAR_WIDTH_ICON,
    } from './constants.js';
    import { setSidebar } from './context.svelte.js';

    import { Tooltip } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        open = $bindable(true),
        onOpenChange = () => {},
        class: className,
        style,
        children,
        withoutCookie = false,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> & {
        open?: boolean;
        onOpenChange?: (open: boolean) => void;
        withoutCookie?: boolean;
    } = $props();

    const sidebarCookie = $derived.by(() => {
        if (typeof document === 'undefined') return;

        // Will return 'sidebar:state={boolean}' or undefined
        const cookie = document?.cookie
            .split(';')
            .find((cookie) => cookie.startsWith(`${SIDEBAR_COOKIE_NAME}=`));

        const value = cookie?.split('=')[1];

        return value == undefined ? undefined : value == 'true';
    });

    open = withoutCookie || sidebarCookie == undefined ? open : sidebarCookie;

    const sidebar = setSidebar({
        open: () => open,
        setOpen: (value: boolean) => {
            open = value;
            onOpenChange(value);

            // This sets the cookie to keep the sidebar state.
            document.cookie = `${SIDEBAR_COOKIE_NAME}=${open}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`;

            onOpenChange(open);
        },
    });
</script>

<svelte:window onkeydown={sidebar.handleShortcutKeydown} />

<Tooltip.Provider delayDuration={0}>
    <div
        bind:this={ref}
        data-slot="sidebar-wrapper"
        style="--sidebar-width: {SIDEBAR_WIDTH}; --sidebar-width-icon: {SIDEBAR_WIDTH_ICON}; {style}"
        class={cn(
            'group/sidebar-wrapper has-data-[variant=inset]:bg-sidebar flex min-h-svh w-full',
            className
        )}
        {...restProps}>
        {@render children?.()}
    </div>
</Tooltip.Provider>
