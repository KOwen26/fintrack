<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        wrapperProps?: HTMLAttributes<HTMLElement>;

        headerClass?: string;
        headerProps?: HTMLAttributes<HTMLElement>;
        headerActionClass?: string;
        header?: Snippet;
        headerAction?: Snippet;

        titleClass?: string;
        title?: string | Snippet;

        footerClass?: string;
        footerProps?: HTMLAttributes<HTMLElement>;
        footer?: Snippet;

        class?: string;
        contentClass?: string;
    }

    let {
        wrapperProps = {},

        headerClass,
        headerActionClass,
        headerProps = {},
        header,
        headerAction,

        titleClass,
        title,

        footerClass,
        footerProps = {},
        footer,

        contentClass,
        class: _class,
        children,
        ...props
    }: Props = $props();
</script>

<div
    data-slot="card"
    class={cn(
        // 'bg-card text-card-foreground flex flex-col gap-5 rounded-md border border-neutral-500 py-5',
        'card card-border @container/card gap-5 bg-white p-5',
        _class
    )}
    {...wrapperProps}>
    {#if title || header || headerAction}
        {@render HeaderSnippet()}
    {/if}
    <div data-slot="card-content" class={cn(contentClass)} {...props}>
        {@render children?.()}
    </div>
    {#if footer}
        {@render FooterSnippet()}
    {/if}
</div>

{#snippet HeaderSnippet()}
    <header
        data-slot="card-header"
        class={cn(
            '@container/card-header flex items-center gap-1.5',
            // 'grid auto-rows-min grid-rows-[auto_auto]  px-5 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-5',
            headerClass
        )}
        {...headerProps}>
        {#if typeof title === 'function'}
            {@render title?.()}
        {:else}
            <h2
                data-slot="card-title"
                class={cn('card-title leading-none font-semibold', titleClass)}>
                {title}
            </h2>
        {/if}

        {@render header?.()}

        <div
            data-slot="card-header-action"
            class={cn(
                // 'col-start-2 row-span-2 row-start-1 self-start justify-self-end',
                headerActionClass
            )}>
            {@render headerAction?.()}
        </div>
    </header>
{/snippet}

{#snippet FooterSnippet()}
    <div
        data-slot="card-footer"
        class={cn(
            // 'flex items-center px-5 [.border-t]:pt-5',
            'card-footer',
            footerClass
        )}
        {...footerProps}>
        {@render footer?.()}
    </div>
{/snippet}
