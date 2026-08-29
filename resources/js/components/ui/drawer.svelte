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
        trigger: Snippet;
        children?: Snippet;
    }

    let {
        open = $bindable(false),
        title = '',
        direction = 'bottom',
        triggerClass = '',
        trigger,
        children,
    }: Props = $props();
</script>

<Drawer bind:open {direction}>
    <DrawerTrigger aria-label="See more" class={triggerClass}>
        {@render trigger?.()}
    </DrawerTrigger>
    <DrawerContent>
        {#if direction === 'bottom'}
            <div class="mx-auto mt-2 h-1.5 w-12 rounded-full bg-base-content/20"></div>
        {/if}
        {#if title}
            <DrawerHeader class="flex-row items-center justify-between">
                <DrawerTitle>{title}</DrawerTitle>
                <DrawerClose
                    class="rounded-md border border-base-content/20 p-1.5 text-base-content transition hover:bg-base-200">
                    <i class="iconify text-lg ph--x-bold"></i>
                </DrawerClose>
            </DrawerHeader>
        {/if}
        {@render children?.()}
    </DrawerContent>
</Drawer>
