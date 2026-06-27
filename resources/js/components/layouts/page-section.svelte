<script lang="ts">
    import type { Snippet } from 'svelte';

    interface Props {
        breakMargin?: boolean;
        children: Snippet;
    }

    let { breakMargin = false, children }: Props = $props();
</script>

<section class={breakMargin ? '-mx-5 md:mx-0' : ''}>
    {@render children()}
</section>
