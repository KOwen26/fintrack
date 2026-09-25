<script lang="ts">
    import type { HeaderContext } from '@type/header-context';

    import { setLayoutProps } from '@inertiajs/svelte';

    let { children }: { children?: HeaderContext['render'] } = $props();

    // Register the page's context snippet, then clear it on unmount: normal
    // visits reset layout props automatically, but preserved-state visits do
    // not, and no page should inherit the previous page's context.
    $effect(() => {
        if (children) {
            setLayoutProps({ headerContext: { type: 'custom', render: children } });
        }

        return () => setLayoutProps({ headerContext: undefined });
    });
</script>
