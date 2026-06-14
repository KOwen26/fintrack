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

    const emblaCtx = getEmblaContext('<Carousel.Next/>');
</script>

<Button
    class={cn(
        'absolute size-8 rounded-full',
        emblaCtx.orientation === 'horizontal'
            ? 'top-1/2 -right-12 -translate-y-1/2'
            : '-bottom-12 left-1/2 -translate-x-1/2 rotate-90',
        className
    )}
    aria-disabled={!emblaCtx.canScrollNext}
    data-slot="carousel-next"
    onclick={emblaCtx.scrollNext}
    onkeydown={emblaCtx.handleKeyDown}
    {size}
    {variant}
    bind:ref
    {...restProps}>
    <i class="iconify ph--arrow-right-duotone size-4"></i>
    <span class="sr-only">Next slide</span>
</Button>
