<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { ComponentProps } from 'svelte';

    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/card.svelte';

    let { children, wrapperClass, ...props }: ComponentProps<typeof Card> = $props();
</script>

<Card
    {...props}
    headerClass="mx-2.5 md:mx-0"
    titleClass="grow"
    wrapperClass={cn(
        '-mx-2.5 md:mx-0',
        'p-5 md:p-6',
        'border-y border-x-none md:border rounded-none md:rounded-md',
        wrapperClass
    )}>
    {@render children?.()}
</Card>
