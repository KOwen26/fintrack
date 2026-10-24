<script lang="ts">
    import type { ToasterProps } from 'svelte-sonner';

    import { IsMobile } from '@lib/is-mobile.svelte';
    import { Toaster } from 'svelte-sonner';

    let { style, ...props }: ToasterProps = $props();

    /** The app's shared mobile definition — toast stacking follows it. */
    const isMobile = new IsMobile();

    const debugToasts = false;

    const richColorsStyle = $derived(
        [
            '--normal-bg: var(--color-card)',

            '--success-bg: var(--color-card)',
            // '--success-border: color-mix(in oklab, var(--color-success) 25%, var(--color-card))',
            '--success-border: var(--color-success)',
            '--success-text: var(--color-success)',

            '--info-bg: var(--color-card)',
            // '--info-border: color-mix(in oklab, var(--color-info) 25%, var(--color-card))',
            '--info-border: var(--color-info)',
            '--info-text: var(--color-info)',

            '--warning-bg: var(--color-card)',
            // '--warning-border: color-mix(in oklab, var(--color-warning) 25%, var(--color-card))',
            '--warning-border: var(--color-warning)',
            '--warning-text: var(--color-warning)',

            '--error-bg: var(--color-card)',
            // '--error-border: color-mix(in oklab, var(--color-error) 25%, var(--color-card))',
            '--error-border: var(--color-error)',
            '--error-text: var(--color-error)',

            // Close button to the right: sonner parameterizes its corner through
            // these vars (LTR = left by default; RTL already ends right), so a
            // root-level inline override flips them without touching any rules.
            '--toast-close-button-start: unset',
            '--toast-close-button-end: 0',
            '--toast-close-button-transform: translate(35%, -35%)',

            ...(style ? [style] : []),
        ].join('; ')
    );

    function secondsToMs(seconds: number) {
        return seconds * 1_000;
    }
</script>

<Toaster
    style={richColorsStyle}
    class="toaster group"
    closeButton
    duration={debugToasts ? secondsToMs(300) : secondsToMs(2)}
    expand={!isMobile.current}
    gap={8}
    position="top-right"
    richColors
    toastOptions={{ class: 'border-[2px]! p-3!' }}
    visibleToasts={isMobile.current ? 2 : 3}
    {...props}>
    {#snippet loadingIcon()}
        <i class="iconify size-6 animate-spin solar--refresh-bold-duotone"></i>
    {/snippet}

    {#snippet successIcon()}
        <i class="iconify size-6 solar--check-circle-bold-duotone"></i>
    {/snippet}

    {#snippet errorIcon()}
        <i class="iconify size-6 solar--danger-circle-bold-duotone"></i>
    {/snippet}

    {#snippet infoIcon()}
        <i class="iconify size-6 solar--info-circle-bold-duotone"></i>
    {/snippet}

    {#snippet warningIcon()}
        <i class="iconify size-6 solar--danger-triangle-bold-duotone"></i>
    {/snippet}
</Toaster>
