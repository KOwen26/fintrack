<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import {
        CardAction,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
        Root,
    } from './atoms/card';

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

        descriptionClass?: string;
        description?: string | Snippet;

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

        descriptionClass,
        description,

        footerClass,
        footerProps = {},
        footer,

        contentClass,
        class: _class,
        children,
        ...props
    }: Props = $props();
</script>

<Root class={cn('@container/card', _class)} {...wrapperProps}>
    {#if title || header || headerAction || description}
        <CardHeader class={headerClass} {...headerProps}>
            {#if typeof title === 'function'}
                {@render title()}
            {:else if title}
                <CardTitle class={cn('flex items-center gap-2 text-lg', titleClass)}>
                    {title}
                </CardTitle>
            {/if}

            {#if typeof description === 'function'}
                {@render description()}
            {:else if description}
                <CardDescription class={descriptionClass}>
                    {description}
                </CardDescription>
            {/if}

            {@render header?.()}

            {#if headerAction}
                <CardAction class={headerActionClass}>
                    {@render headerAction()}
                </CardAction>
            {/if}
        </CardHeader>
    {/if}

    <CardContent class={contentClass} {...props}>
        {@render children?.()}
    </CardContent>

    {#if footer}
        <CardFooter class={footerClass} {...footerProps}>
            {@render footer()}
        </CardFooter>
    {/if}
</Root>
