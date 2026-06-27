<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/card.svelte';

    let { children, class: _class, ...props }: ComponentProps<typeof Card> = $props();
</script>

<Card
    {...props}
    class={cn(
        '-mx-2.5 md:mx-0',
        'p-5 md:p-6',
        'border-y border-x-none md:border rounded-none md:rounded-md',
        _class
    )}
    headerClass="mx-2.5 md:mx-0"
    titleClass="grow">
    {@render children?.()}
</Card>
