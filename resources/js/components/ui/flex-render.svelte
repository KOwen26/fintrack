<script generics="TContext = unknown" lang="ts">
    import { RenderComponentConfig, RenderSnippetConfig } from '@utilities/render-helper';

    interface Props {
        /** Static text, or a function of `context` returning text, a `RenderComponentConfig`, or a `RenderSnippetConfig`. */
        content?: string | ((context: TContext) => unknown);
        /** The value handed to the content function. Only needed when `content` is a function. */
        context?: TContext;
    }

    let { content, context = {} as TContext }: Props = $props();
</script>

{#if typeof content === 'string'}
    <span>{content}</span>
{:else if content instanceof Function}
    {@const result = content(context)}

    {#if result instanceof RenderComponentConfig}
        {@const { component: Component, props } = result}

        <Component {...props} />
    {:else if result instanceof RenderSnippetConfig}
        {@const { snippet, params } = result}

        {@render snippet(params)}
    {:else}
        <span>{result}</span>
    {/if}
{/if}
