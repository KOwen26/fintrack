<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/card.svelte';

    let { children, class: _class, ...props }: ComponentProps<typeof Card> = $props();
</script>

<Card
    {...props}
    class={cn('', 'md:py-6', 'border-x-none rounded-none border-y md:rounded-md md:border', _class)}
    headerClass="px-8 md:px-6"
    titleClass="grow">
    {@render children?.()}
</Card>
