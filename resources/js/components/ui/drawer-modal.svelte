<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    export interface DrawerModalProps extends RestProps {
        open?: boolean;
        title?: string;
        direction?: 'top' | 'bottom' | 'left' | 'right';
        trigger?: Snippet;
        triggerClass?: string;
        header?: Snippet<[closeButton: Snippet]>;
        footer?: Snippet<[closeButton: Snippet]>;
        actionButton?: Snippet;
        contentClass?: string;
    }
</script>

<script lang="ts">
    import { IsMobile } from '@/svelte/is-mobile.svelte.js';

    import { cn } from '@utilities/shadcn';

    import {
        Drawer,
        DrawerClose,
        DrawerContent,
        DrawerFooter,
        DrawerHeader,
        DrawerTitle,
        DrawerTrigger,
    } from '@components/ui/atoms/drawer';
    import Button from '@components/ui/button.svelte';
    import Modal from '@components/ui/modals/modal.svelte';

    type SurfaceMode = 'mobile' | 'desktop';

    let {
        open = $bindable(false),
        title = '',
        direction = 'bottom',
        trigger,
        triggerClass = '',
        header,
        footer,
        actionButton,
        contentClass = '',
        children,
        ...props
    }: DrawerModalProps = $props();

    // Bottom drawer on mobile, centered modal on desktop. While closed, `mode` tracks the
    // viewport; the first evaluation while open memoizes it, so resizing mid-interaction
    // never morphs the surface. The viewport is always read to keep the derived reactive.
    const isMobile = new IsMobile();
    let modeWhenOpen: SurfaceMode | null = null;

    const mode = $derived.by(() => {
        const current: SurfaceMode = isMobile.current ? 'mobile' : 'desktop';

        if (!open) {
            modeWhenOpen = null;

            return current;
        }

        modeWhenOpen ??= current;

        return modeWhenOpen;
    });
</script>

{#if mode === 'mobile'}
    <Drawer {direction} bind:open {...props}>
        {#if trigger}
            <DrawerTrigger class={triggerClass} aria-label={title || undefined}>
                {@render trigger()}
            </DrawerTrigger>
        {/if}
        <DrawerContent class={cn(contentClass)}>
            {#if header}
                {@render header(headerCloseButton)}
            {:else}
                {@render defaultHeader(headerCloseButton)}
            {/if}
            <div class={cn('min-h-0 flex-1 overflow-y-auto', props.class)}>
                {@render children?.()}
            </div>
            {#if footer}
                {@render footer(footerCloseButton)}
            {:else}
                {@render defaultFooter()}
            {/if}
        </DrawerContent>
    </Drawer>
{:else}
    <Modal
        {actionButton}
        footer={footer ?? (actionButton ? actionButtonFooter : undefined)}
        {header}
        {title}
        bind:open
        {...props}>
        {@render children?.()}
    </Modal>
{/if}

{#snippet headerCloseButton()}
    <DrawerClose
        class="rounded-md border border-base-content/20 p-1.5 text-base-content transition hover:bg-base-200">
        <i class="iconify text-lg solar--close-bold-duotone"></i>
    </DrawerClose>
{/snippet}

{#snippet footerCloseButton()}
    <DrawerClose>
        <Button color="light" variant="outline">Tutup</Button>
    </DrawerClose>
{/snippet}

{#snippet actionButtonFooter(closeButton: Snippet)}
    <div class="flex w-full justify-end gap-3 p-5 px-5">
        {@render closeButton?.()}
        {@render actionButton?.()}
    </div>
{/snippet}

{#snippet defaultHeader(closeButton: Snippet)}
    {#if title}
        <DrawerHeader class="flex-row items-center justify-between">
            <DrawerTitle>{title}</DrawerTitle>
            {@render closeButton?.()}
        </DrawerHeader>
    {/if}
{/snippet}

{#snippet defaultFooter()}
    {#if actionButton}
        <DrawerFooter>
            {@render actionButton()}
        </DrawerFooter>
    {/if}
{/snippet}
