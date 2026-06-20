<script lang="ts">
    import type { RestProps } from '@type/index';

    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        mode?: string;
    }

    let { mode, children, ...props }: Props = $props();
</script>

<Card {...props}>
    {@render children?.()}
</Card>
