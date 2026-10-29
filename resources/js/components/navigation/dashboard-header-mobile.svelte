<script lang="ts">
    import type { HeaderContext } from '@type/header-context';

    import { backOrRedirectTo } from '@lib/navigation-helper.svelte';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    interface Props {
        backUrl?: string | (() => void);
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

    // A function backUrl is the page's own handler; a plain URL gets the shared
    // smart-back behavior (history when an in-app page sits behind).
    const onBack = $derived.by(() => {
        if (backUrl === undefined) return undefined;

        return typeof backUrl === 'function' ? backUrl : backOrRedirectTo(backUrl);
    });

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
    safe-area aware, and mobile page padding. `actions` context augments the
    shared back/title; `custom` context remains fully page-owned.
-->
<header
    data-slot="header-mobile"
    class={cn(
        'flex min-h-14 items-center gap-2 px-4 pt-[env(safe-area-inset-top)] md:hidden',
        _class,
        scrolledClass && scrolled && scrolledClass
    )}>
    {#if headerContext?.type === 'custom'}
        <div class="flex min-w-0 flex-1 items-center gap-2">
            {@render headerContext.render()}
        </div>
    {:else}
        {#if onBack}
            <Button color="primary" onclick={() => onBack?.()} size="icon" variant="ghost">
                <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
            </Button>
        {/if}

        {#if title}
            <span class="truncate text-xl font-bold">{title}</span>
        {/if}

        {#if headerContext?.type === 'actions'}
            <div class="ml-auto flex shrink-0 items-center gap-2">
                {@render headerContext.render()}
            </div>
        {/if}
    {/if}
</header>
