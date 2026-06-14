<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        wrapperClass?: string;
        wrapperProps?: HTMLAttributes<HTMLDivElement>;

        headerClass?: string;
        headerProps?: HTMLAttributes<HTMLDivElement>;
        headerActionClass?: string;
        header?: Snippet;
        headerAction?: Snippet;

        titleClass?: string;
        title?: string | Snippet;

        footerClass?: string;
        footerProps?: HTMLAttributes<HTMLDivElement>;
        footer?: Snippet;
    }

    let {
        wrapperClass,
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

        class: _class,
        children,
        ...props
    }: Props = $props();
</script>

<div
    class={cn(
        // 'bg-card text-card-foreground flex flex-col gap-5 rounded-md border border-neutral-500 py-5',
        'card card-border @container/card gap-5 bg-white p-5',
        wrapperClass
    )}
    data-slot="card"
    {...wrapperProps}>
    {#if title || header || headerAction}
        {@render HeaderSnippet()}
    {/if}
    <div class={cn('card-body p-0', _class)} data-slot="card-content" {...props}>
        {@render children?.()}
    </div>
    {#if footer}
        {@render FooterSnippet()}
    {/if}
</div>

{#snippet HeaderSnippet()}
    <header
        class={cn(
            '@container/card-header flex items-center gap-1.5',
            // 'grid auto-rows-min grid-rows-[auto_auto]  px-5 has-data-[slot=card-action]:grid-cols-[1fr_auto] [.border-b]:pb-5',
            headerClass
        )}
        data-slot="card-header"
        {...headerProps}>
        {#if typeof title === 'function'}
            {@render title?.()}
        {:else}
            <h2
                class={cn('card-title leading-none font-semibold', titleClass)}
                data-slot="card-title">
                {title}
            </h2>
        {/if}
        {@render header?.()}
        <div
            class={cn(
                // 'col-start-2 row-span-2 row-start-1 self-start justify-self-end',
                headerActionClass
            )}
            data-slot="card-header-action">
            {@render headerAction?.()}
        </div>
    </header>
{/snippet}

{#snippet FooterSnippet()}
    <div
        class={cn(
            // 'flex items-center px-5 [.border-t]:pt-5',
            'card-footer',
            footerClass
        )}
        data-slot="card-footer"
        {...footerProps}>
        {@render footer?.()}
    </div>
{/snippet}
