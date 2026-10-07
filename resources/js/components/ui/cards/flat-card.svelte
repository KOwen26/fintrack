<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn';

    /**
     * Card without the paint: same contract, DOM shape, `data-slot` markers,
     * groups, container queries, grid/flex layout, spacing scale, and
     * typographic sizing as `Card` — but no backgrounds, rings, borders,
     * radius, or foreground colors.
     */
    interface Props extends RestProps {
        wrapperProps?: HTMLAttributes<HTMLElement>;

        size?: 'default' | 'sm';

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

        size = 'default',

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

<div
    data-slot="card"
    class={cn(
        'group/card @container/card flex flex-col gap-(--card-spacing) py-(--card-spacing) text-sm [--card-spacing:--spacing(4)] has-data-[slot=card-footer]:pb-0 has-[>img:first-child]:pt-0 data-[size=sm]:[--card-spacing:--spacing(3)] data-[size=sm]:has-data-[slot=card-footer]:pb-0',
        _class
    )}
    data-size={size}
    {...wrapperProps}>
    {#if title || header || headerAction || description}
        <div
            data-slot="card-header"
            class={cn(
                'group/card-header @container/card-header grid auto-rows-min items-start gap-1 px-(--card-spacing) has-data-[slot=card-action]:grid-cols-[1fr_auto] has-data-[slot=card-description]:grid-rows-[auto_auto] [.border-b]:pb-(--card-spacing)',
                headerClass
            )}
            {...headerProps}>
            {#if typeof title === 'function'}
                {@render title()}
            {:else if title}
                <h3
                    data-slot="card-title"
                    class={cn(
                        'flex items-center gap-2 text-lg leading-snug font-medium group-data-[size=sm]/card:text-sm',
                        titleClass
                    )}>
                    {title}
                </h3>
            {/if}

            {#if typeof description === 'function'}
                {@render description()}
            {:else if description}
                <p data-slot="card-description" class={cn('text-sm', descriptionClass)}>
                    {description}
                </p>
            {/if}

            {@render header?.()}

            {#if headerAction}
                <div
                    data-slot="card-action"
                    class={cn(
                        'col-start-2 row-span-2 row-start-1 self-start justify-self-end',
                        headerActionClass
                    )}>
                    {@render headerAction()}
                </div>
            {/if}
        </div>
    {/if}

    <div data-slot="card-content" class={cn('px-(--card-spacing)', contentClass)} {...props}>
        {@render children?.()}
    </div>

    {#if footer}
        <div
            data-slot="card-footer"
            class={cn('flex items-center p-(--card-spacing)', footerClass)}
            {...footerProps}>
            {@render footer()}
        </div>
    {/if}
</div>
