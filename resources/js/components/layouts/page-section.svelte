<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        breakMargin?: boolean;
        children: Snippet;
    }

    let { breakMargin = false, class: _class, children }: Props = $props();
</script>

<section class={cn(breakMargin ? '-mx-5 md:mx-0' : '', _class)}>
    {@render children()}
</section>
