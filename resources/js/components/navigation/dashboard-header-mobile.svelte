<script lang="ts">
    import type { HeaderContext } from '@type/header-context';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    interface Props {
        backUrl?: string;
        headerContext?: HeaderContext;
        title?: string;
        class?: string;
        scrolledClass?: string;
        scrollThreshold?: number;
    }

    let {
        backUrl = undefined,
        headerContext = undefined,
        title = undefined,
        class: _class,
        scrolledClass = undefined,
        scrollThreshold = 12,
    }: Props = $props();

    /* Scroll-aware styling: the header stays bare (e.g. transparent) until the
       page scrolls past the threshold, then the scrolled classes fade in. */
    let scrolled = $state(false);

    $effect(() => {
        if (!scrolledClass) return;

        const onScroll = () => {
            scrolled = window.scrollY > scrollThreshold;
        };

        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    });
</script>

<!--
    Mobile context bar. A separate design from the desktop header: flat,
    safe-area aware, mobile page padding, and fully owned by the page context
    when one is declared.
-->
<header
    data-slot="header-mobile"
    class={cn(
        'flex min-h-14 items-center gap-2 px-3 pt-[env(safe-area-inset-top)] text-base-content md:hidden',
        _class,
        scrolledClass && scrolled && scrolledClass
    )}>
    {#if headerContext}
        <div class="flex min-w-0 flex-1 items-center gap-2">
            {@render headerContext.render()}
        </div>
    {:else}
        {#if backUrl}
            <Button
                class="size-10 shrink-0 p-1 btn-sm"
                color="secondary"
                href={backUrl}
                variant="ghost">
                <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
            </Button>
        {/if}
        {#if title}
            <span class="truncate text-xl font-bold">{title}</span>
        {/if}
    {/if}
</header>
