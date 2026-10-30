<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { IsMobile } from '@lib/is-mobile.svelte';
    import { mergeProps } from 'bits-ui';

    import { cn } from '@utilities/shadcn';

    import Card from '@components/ui/cards/card.svelte';

    interface ResponsiveCard extends ComponentProps<typeof Card> {
        onMobileStyle?: 'card' | 'none';
    }

    let { onMobileStyle = 'none', children, class: _class, ...props }: ResponsiveCard = $props();

    const mobile = new IsMobile();

    const mergedProps = $derived(
        mergeProps({
            ...props,
            class: _class,
            headerClass: cn('px-0', props.headerClass),
            titleClass: cn('font-semibold', props.titleClass),
            contentClass: cn('px-0', props.contentClass),
        })
    );
</script>

<Card variant={mobile.current && onMobileStyle === 'none' ? 'flat' : 'default'} {...mergedProps}>
    {@render children?.()}
</Card>
