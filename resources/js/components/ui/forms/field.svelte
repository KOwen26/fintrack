<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    export type FieldProps = {
        title?: string;
        required?: boolean;
        formMode?: 'form' | 'data';

        value?: string | number | Snippet;
        valueClass?: string;

        error?: string | string[];
        errorSnippet?: Snippet<[errorMessage: string]>;

        notes?: string | string[] | Snippet;

        class?: RestProps['class'];
    };
</script>

<script lang="ts">
    let {
        title,
        error: _errorMessage,
        children,
        formMode = 'form',
        required = false,
        value = '-',
        notes: _notes = undefined,
        errorSnippet = defaultError,
        valueClass = '',
        class: _class,
        ...props
    }: FieldProps & RestProps = $props();

    const uid = $props.id();
    const id = $derived(props?.id ?? uid);

    const errorMessage = $derived(
        Array.isArray(_errorMessage) ? _errorMessage?.join('\n ')?.trim() : _errorMessage
    );

    const notes = $derived(
        typeof _notes === 'function' || typeof _notes === 'string'
            ? _notes
            : _notes?.join('\n ').trim()
    );
</script>

<fieldset data-slot="field" class={['space-y-1.5', _class]}>
    {#if title}
        <label data-slot="field-label" class="inline-block text-sm font-medium text-black" for={id}>
            {title}
            {#if required}
                <span class="text-error align-text-top">*</span>
            {/if}
        </label>
    {/if}
    <div data-slot="field-content" class={valueClass}>
        {#if formMode === 'form'}
            {@render children?.()}
        {:else if typeof value === 'function'}
            {@render value?.()}
        {:else}
            {value ?? '-'}
        {/if}
    </div>
    {#if !!notes}
        <div data-slot="field-notes">
            {#if typeof notes === 'function'}
                {@render notes?.()}
            {:else}
                <p class="text-xs whitespace-pre-line text-gray-500">{notes}</p>
            {/if}
        </div>
    {/if}
    {#if errorMessage}
        <div data-slot="field-error">
            {@render errorSnippet?.(errorMessage)}
        </div>
    {/if}
</fieldset>

{#snippet defaultError(errorMessage: string)}
    <p class="text-error text-xs whitespace-pre-wrap">{errorMessage}</p>
{/snippet}
