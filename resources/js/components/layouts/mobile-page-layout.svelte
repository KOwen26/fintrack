<script lang="ts">
    import type { Snippet } from 'svelte';

    import { setLayoutProps } from '@inertiajs/svelte';

    import { cn } from '@utilities/shadcn';

    type SheetVariant = 'full' | '1/2' | '1/5' | '2/5' | '3/5' | '4/5';

    interface Props {
        /** Mobile shell classes — the wrapper must share the hero ground so
            the context-bar zone blends in. */
        shellClass?: string;
        /** The sheet's share of the screen; the hero takes the rest. */
        variant?: SheetVariant;
        /** Content for the bg-secondary hero region. */
        hero?: Snippet;
        /** Core content — rendered inside the sheet. */
        children: Snippet;
        class?: string;
        heroClass?: string;
        contentClass?: string;
    }

    let {
        shellClass = 'bg-secondary',
        variant = 'full',
        hero,
        children,
        class: _class,
        heroClass,
        contentClass,
    }: Props = $props();

    /* Literal classes so Tailwind generates every variant — the split only
       sizes the two regions; scrolling stays the page's decision. */
    const heroClasses: Record<SheetVariant, string> = {
        full: 'hidden',
        '1/2': 'flex-1 min-h-0',
        '1/5': 'flex-4 min-h-0',
        '2/5': 'flex-3 min-h-0',
        '3/5': 'flex-2 min-h-0',
        '4/5': 'flex-1 min-h-0',
    };

    const sheetClasses: Record<SheetVariant, string> = {
        full: 'flex-1',
        '1/2': 'flex-1 min-h-0 rounded-t-2xl',
        '1/5': 'flex-1 min-h-0 rounded-t-2xl',
        '2/5': 'flex-2 min-h-0 rounded-t-2xl',
        '3/5': 'flex-3 min-h-0 rounded-t-2xl',
        '4/5': 'flex-4 min-h-0 rounded-t-2xl',
    };

    // Register the shell class (tracked), and clear it on unmount — preserved-
    // state visits keep layout props, so no page should inherit ours.
    $effect(() => {
        setLayoutProps({ mobileShellClass: variant === 'full' ? undefined : shellClass });

        return () => setLayoutProps({ mobileShellClass: undefined });
    });
</script>

<div
    class={cn(
        'relative flex min-h-dvh w-full flex-col gap-6 bg-secondary text-secondary-content antialiased',
        _class
    )}>
    {#if hero}
        <div data-slot="mobile-page-layout-hero" class={cn(heroClasses[variant], heroClass)}>
            {@render hero()}
        </div>
    {/if}

    <section
        data-slot="mobile-page-layout-content"
        class={cn(
            'relative bg-base-100 px-5 pt-5 pb-10 text-base-content',
            sheetClasses[variant],
            contentClass
        )}>
        {@render children()}
    </section>
</div>
