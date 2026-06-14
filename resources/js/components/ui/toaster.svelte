<script lang="ts">
    import type { ToasterProps } from 'svelte-sonner';

    import { Toaster } from 'svelte-sonner';

    // import { mode } from 'mode-watcher';

    let { ...restProps }: ToasterProps = $props();
</script>

<!-- <Toaster
    style="--normal-bg: var(--color-popover); --normal-text: var(--color-popover-foreground); --normal-border: var(--color-border);"
    class="toaster group"
    theme={mode.current}
    {...restProps} /> -->

<Toaster
    class="toaster"
    closeButton
    duration={2000}
    expand
    gap={16}
    position="top-right"
    richColors />
