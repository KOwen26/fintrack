<script lang="ts" module>
    import { Collapsible as CollapsiblePrimitive } from 'bits-ui';

    export type CollapsibleProps = {
        rootRef?: HTMLElement | null;
        triggerRef?: HTMLElement | null;
        contentRef?: HTMLElement | null;
        open?: boolean;
        disabled?: boolean;
        trigger: string | Snippet;
        children?: Snippet<[{ trigger: Snippet }]>;
        content: Snippet;
    } & CollapsiblePrimitive.RootProps &
        HTMLAttributes<HTMLDivElement>;
</script>

<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    let {
        rootRef = $bindable(null),
        triggerRef = $bindable(null),
        contentRef = $bindable(null),
        open = $bindable(false),
        disabled,
        trigger = $bindable(triggerSnippet),
        content,
        children,
        ...restProps
    }: CollapsibleProps = $props();
</script>

<CollapsiblePrimitive.Root
    data-slot="collapsible"
    {disabled}
    bind:ref={rootRef}
    bind:open
    {...restProps}>
    {#if typeof children === 'function'}
        {@render children?.({ trigger: triggerSnippet })}
    {:else}
        {@render triggerSnippet()}
    {/if}
    <CollapsiblePrimitive.Content data-slot="collapsible-content" bind:ref={contentRef}>
        {@render content?.()}
    </CollapsiblePrimitive.Content>
</CollapsiblePrimitive.Root>

{#snippet triggerSnippet()}
    <CollapsiblePrimitive.Trigger data-slot="collapsible-trigger" bind:ref={triggerRef}>
        {#if typeof trigger === 'function'}
            {@render trigger?.()}
        {:else}
            {trigger}
        {/if}
    </CollapsiblePrimitive.Trigger>
{/snippet}
