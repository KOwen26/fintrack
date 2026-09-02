<script lang="ts">
    import type { WithoutChildren } from 'bits-ui';

    import { getEmblaContext } from './context.js';

    import { cn } from '@utilities/shadcn.js';

    import Button from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        variant = 'outline',
        size = 'icon',
        ...restProps
    }: WithoutChildren<Props> = $props();

    const emblaCtx = getEmblaContext('<Carousel.Previous/>');
</script>

<Button
    data-slot="carousel-previous"
    class={cn(
        'absolute size-8 rounded-full',
        emblaCtx.orientation === 'horizontal'
            ? 'top-1/2 -left-12 -translate-y-1/2'
            : '-top-12 left-1/2 -translate-x-1/2 rotate-90',
        className
    )}
    aria-disabled={!emblaCtx.canScrollPrev}
    onclick={emblaCtx.scrollPrev}
    onkeydown={emblaCtx.handleKeyDown}
    {size}
    {variant}
    {...restProps}
    bind:ref>
    <i class="iconify size-4 solar--arrow-left-line-duotone"></i>
    <span class="sr-only">Previous slide</span>
</Button>
