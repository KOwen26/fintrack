<script lang="ts">
    import type { Snippet } from 'svelte';

    import {
        Drawer,
        DrawerClose,
        DrawerContent,
        DrawerHeader,
        DrawerTitle,
        DrawerTrigger,
    } from '@components/ui/atoms/drawer';

    interface Props {
        open?: boolean;
        title?: string;
        direction?: 'top' | 'bottom' | 'left' | 'right';
        triggerClass?: string;
        /** Set false to omit the dimming overlay behind the sheet. */
        overlay?: boolean;
        /** Omit to control the drawer only through `bind:open`. */
        trigger?: Snippet;
        children?: Snippet;
    }

    let {
        open = $bindable(false),
        title = '',
        direction = 'bottom',
        triggerClass = '',
        overlay = true,
        trigger,
        children,
    }: Props = $props();
</script>

<Drawer {direction} bind:open>
    {#if trigger}
        <DrawerTrigger class={triggerClass} aria-label="See more">
            {@render trigger?.()}
        </DrawerTrigger>
    {/if}
    <DrawerContent class="border-muted drop-shadow-2xl/50" {overlay}>
        {#if title}
            <DrawerHeader class="flex-row items-center justify-between">
                <DrawerTitle>{title}</DrawerTitle>
                <DrawerClose
                    class="rounded-md border border-base-content/20 p-1.5 text-base-content transition hover:bg-base-200">
                    <i class="iconify text-lg solar--close-bold-duotone"></i>
                </DrawerClose>
            </DrawerHeader>
        {/if}
        {@render children?.()}
    </DrawerContent>
</Drawer>
