<script lang="ts">
    import type { ComponentProps, Snippet } from 'svelte';

    import { Link } from '@inertiajs/svelte';

    interface Props extends ComponentProps<typeof Link> {
        children: Snippet;
    }

    let { children, ...props }: Props = $props();
</script>

<Link {...props}>
    {@render children?.()}
</Link>
