<script lang="ts" module>
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { ScrollArea as ScrollAreaPrimitive } from 'bits-ui';

    const baseRootClass = 'relative';
    const baseViewportClass =
        'ring-ring/10 dark:ring-ring/20 dark:outline-ring/40 outline-ring/50 size-full rounded-[inherit] transition-[color,box-shadow] focus-visible:ring-4 focus-visible:outline-1';

    const baseScrollbarClass = 'flex touch-none p-px transition-colors select-none';
    const scrollbarOrientationClass = {
        vertical: 'h-full w-2.5 border-l border-l-transparent',
        horizontal: 'h-2.5 flex-col border-t border-t-transparent',
    };
    const baseThumbClass = 'bg-border relative flex-1 rounded-full';

    export type ScrollAreaProps = {
        rootRef?: HTMLElement | null;
        viewportRef?: HTMLElement | null;
        orientation?: 'vertical' | 'horizontal' | 'both';
        rootClass?: string;
        scrollbarXClasses?: string;
        scrollbarYClasses?: string;
        children?: Snippet;
        scrollbar?: Snippet<[{ orientation: ScrollAreaPrimitive.ScrollbarProps['orientation'] }]>;
    } & ScrollAreaPrimitive.RootProps &
        HTMLAttributes<HTMLDivElement>;
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn.js';

    let {
        rootRef = $bindable(null),
        viewportRef = $bindable(null),
        rootClass: _rootClass,
        class: _class,
        orientation = 'vertical',
        scrollbarXClasses = '',
        scrollbarYClasses = '',
        children,
        scrollbar = scrollbarSnippet,
        ...restProps
    }: ScrollAreaProps = $props();

    const rootClass = $derived(cn(baseRootClass, _rootClass));
    const viewportClass = $derived(cn(baseViewportClass, _class));
    const scrollbarVerticalClass = $derived(
        cn(baseScrollbarClass, scrollbarOrientationClass.vertical, scrollbarYClasses)
    );
    const scrollbarHorizontalClass = $derived(
        cn(baseScrollbarClass, scrollbarOrientationClass.horizontal, scrollbarXClasses)
    );
</script>

<ScrollAreaPrimitive.Root
    data-slot="scroll-area"
    class={rootClass}
    bind:ref={rootRef}
    {...restProps}>
    <ScrollAreaPrimitive.Viewport
        data-slot="scroll-area-viewport"
        class={viewportClass}
        bind:ref={viewportRef}>
        {@render children?.()}
    </ScrollAreaPrimitive.Viewport>

    {#if orientation === 'vertical' || orientation === 'both'}
        {@render scrollbar?.({ orientation: orientation === 'both' ? 'vertical' : orientation })}
    {/if}

    {#if orientation === 'horizontal' || orientation === 'both'}
        {@render scrollbar?.({ orientation: orientation === 'both' ? 'horizontal' : orientation })}
    {/if}

    <ScrollAreaPrimitive.Corner />
</ScrollAreaPrimitive.Root>

{#snippet scrollbarSnippet({ orientation })}
    <ScrollAreaPrimitive.Scrollbar
        data-slot="scroll-area-scrollbar"
        class={orientation === 'vertical' ? scrollbarVerticalClass : scrollbarHorizontalClass}
        {orientation}>
        <ScrollAreaPrimitive.Thumb data-slot="scroll-area-thumb" class={baseThumbClass} />
    </ScrollAreaPrimitive.Scrollbar>
{/snippet}
