<script lang="ts">
    import type { BottomActionBarContext } from '@type/bottom-action-bar';

    import { setLayoutProps } from '@inertiajs/svelte';

    let { children }: { children?: BottomActionBarContext['render'] } = $props();

    // Register the page's bottom action, then clear it on unmount: preserved-
    // state visits keep layout props, so no page should inherit ours. While
    // set, the mobile shell renders this bar instead of the dock.
    $effect(() => {
        if (children) {
            setLayoutProps({ bottomActionBar: { type: 'custom', render: children } });
        }

        return () => setLayoutProps({ bottomActionBar: undefined });
    });
</script>
