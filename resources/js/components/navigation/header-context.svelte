<script lang="ts">
    import type { HeaderContext } from '@type/header-context';
    import type { Snippet } from 'svelte';

    import { setLayoutProps } from '@inertiajs/svelte';

    let {
        children,
        actions,
    }: {
        children?: HeaderContext['render'];
        actions?: Snippet;
    } = $props();

    // Register the page's context snippet, then clear it on unmount: normal
    // visits reset layout props automatically, but preserved-state visits do
    // not, and no page should inherit the previous page's context.
    $effect(() => {
        const render = actions ?? children;

        if (render) {
            setLayoutProps({
                headerContext: {
                    type: actions ? 'actions' : 'custom',
                    render,
                },
            });
        }

        return () => setLayoutProps({ headerContext: undefined });
    });
</script>
