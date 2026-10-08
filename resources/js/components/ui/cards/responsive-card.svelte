<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import FlatCard from './flat-card.svelte';

    import { IsMobile } from '@lib/is-mobile.svelte';
    import { mergeProps } from 'bits-ui';

    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/card.svelte';

    interface ResponsiveCard extends ComponentProps<typeof Card> {
        onMobileStyle?: 'card' | 'none';
    }

    let { onMobileStyle = 'none', children, class: _class, ...props }: ResponsiveCard = $props();

    const mobile = new IsMobile();

    const mergedProps = $derived(
        mergeProps({
            ...props,
            headerClass: cn('px-0', props.headerClass),
            titleClass: cn('font-semibold', props.titleClass),
            contentClass: cn('px-0', props.contentClass),
        })
    );
</script>

{#if mobile.current && onMobileStyle === 'none'}
    <FlatCard {...mergedProps}>
        {@render children?.()}
    </FlatCard>
{:else}
    <Card
        {...mergedProps}
        class={cn(
            '',
            'md:py-6',
            'border-x-none rounded-none border-y md:rounded-md md:border',
            _class
        )}
        headerClass="px-8 md:px-6"
        titleClass="grow">
        {@render children?.()}
    </Card>
{/if}
