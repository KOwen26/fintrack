<script lang="ts">
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLAttributes } from 'svelte/elements';

    import { getEmblaContext } from './context.js';

    import emblaCarouselSvelte from 'embla-carousel-svelte';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        children,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLDivElement>> = $props();

    const emblaCtx = getEmblaContext('<Carousel.Content/>');
</script>

<div
    class="overflow-hidden"
    data-slot="carousel-content"
    onemblaInit={emblaCtx.onInit}
    use:emblaCarouselSvelte={{
        options: {
            container: '[data-embla-container]',
            slides: '[data-embla-slide]',
            ...emblaCtx.options,
            axis: emblaCtx.orientation === 'horizontal' ? 'x' : 'y',
        },
        plugins: emblaCtx.plugins,
    }}>
    <div
        bind:this={ref}
        class={cn(
            'flex',
            emblaCtx.orientation === 'horizontal' ? '-ml-4' : '-mt-4 flex-col',
            className
        )}
        data-embla-container=""
        {...restProps}>
        {@render children?.()}
    </div>
</div>
