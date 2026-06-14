<script lang="ts">
    import type { RestProps } from '@type/index';

    import { twMerge } from 'tailwind-merge';

    import FlexRender from '@components/ui/flex-render.svelte';

    interface Props extends RestProps {
        label: string;
        value?: any;
        class?: string;
        labelClass?: string;
        valueClass?: string;
        children?: any;
    }

    let {
        label,
        value,
        class: _class,
        labelClass = '',
        valueClass = '',
        children,
    }: Props = $props();
</script>

<div class={twMerge('space-y-2', _class)}>
    <h6 class={twMerge('font-medium text-neutral-700/90 capitalize', labelClass)}>
        {label}
    </h6>
    <div class={twMerge('text-wrap wrap-anywhere text-neutral-950', valueClass)}>
        {#if !!children}
            {@render children?.()}
        {:else}
            {@const content = typeof value === 'string' ? value : () => value}

            <FlexRender {content} context={value} />
        {/if}
    </div>
</div>
