<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    import { twMerge } from 'tailwind-merge';

    import FlexRender from '@components/ui/flex-render.svelte';

    interface Props extends RestProps {
        class?: string;
        label: string;
        labelClass?: string;
        value: any;
        valueClass?: string;
        valueSnippet?: Snippet;
    }

    let {
        class: _class,
        label,
        labelClass = '',
        value,
        valueClass = '',
        valueSnippet,
    }: Props = $props();

    const content = $derived(typeof value === 'string' ? value : () => value);
</script>

<div class={twMerge('flex flex-row items-center justify-between gap-5', _class)}>
    <h6 class={twMerge('font-medium text-neutral-700/90 capitalize', labelClass)}>
        {label}
    </h6>
    {#if valueSnippet}
        {@render valueSnippet()}
    {:else}
        <div class={twMerge('text-wrap wrap-anywhere text-neutral-950', valueClass)}>
            <FlexRender {content} context={value} />
        </div>
    {/if}
</div>
