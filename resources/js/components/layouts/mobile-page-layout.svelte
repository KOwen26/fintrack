<script lang="ts">
    import type { Snippet } from 'svelte';

    import { setLayoutProps } from '@inertiajs/svelte';
    import { getCssColor, setStatusBarTheme } from '@lib/theme-handler.svelte';

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
        shellClass = 'bg-secondary text-primary',
        variant = 'full',
        hero,
        children,
        class: _class,
        heroClass,
        contentClass,
    }: Props = $props();

    /* Literal classes so Tailwind generates every variant. The hero is fixed to
       its viewport share; the sheet starts at its share but may grow with
       content, so scrolling stays at the page level. */
    const heroClasses: Record<SheetVariant, string> = {
        full: 'hidden',
        '1/2': 'min-h-[50dvh] shrink-0',
        '1/5': 'min-h-[80dvh] shrink-0',
        '2/5': 'min-h-[60dvh] shrink-0',
        '3/5': 'min-h-[40dvh] shrink-0',
        '4/5': 'min-h-[20dvh] shrink-0',
    };

    const sheetClasses: Record<SheetVariant, string> = {
        full: 'flex-1',
        '1/2': 'min-h-[50dvh] rounded-t-2xl drop-shadow-2xl/50',
        '1/5': 'min-h-[20dvh] rounded-t-2xl drop-shadow-2xl/50',
        '2/5': 'min-h-[40dvh] rounded-t-2xl drop-shadow-2xl/50',
        '3/5': 'min-h-[60dvh] rounded-t-2xl drop-shadow-2xl/50',
        '4/5': 'min-h-[80dvh] rounded-t-2xl drop-shadow-2xl/50',
    };

    // Register the shell class (tracked), and clear it on unmount — preserved-
    // state visits keep layout props, so no page should inherit ours.
    $effect(() => {
        setLayoutProps({ mobileShellClass: variant === 'full' ? undefined : shellClass });

        setStatusBarTheme(getCssColor(variant === 'full' ? '--background' : '--secondary'));

        return () => setLayoutProps({ mobileShellClass: undefined });
    });
</script>

<!-- <svelte:head>
    <meta name="theme-color" content={getCssColor('--secondary')} />
</svelte:head> -->

<!-- Start at the viewport, but let the sheet grow with its content so normal page scrolling is preserved. -->
<div
    class={cn(
        'relative flex min-h-dvh w-full flex-col bg-secondary text-secondary-foreground antialiased',
        _class
    )}>
    <section
        data-slot="mobile-page-layout-hero"
        class={cn(heroClasses[variant], 'px-5 py-10', heroClass)}>
        {@render hero?.()}
    </section>

    <!-- `contentClass` stays on the flex slice; padding lives on the inner wrapper so box padding cannot distort the hero/sheet ratio. -->
    <section
        data-slot="mobile-page-layout-content"
        class={cn('relative bg-background text-foreground', sheetClasses[variant], contentClass)}>
        <div class="p-5 pb-25">
            {@render children()}
        </div>
    </section>
</div>
