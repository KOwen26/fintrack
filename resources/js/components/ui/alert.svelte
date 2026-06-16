<script lang="ts" module>
    export const alertVariants = ['solid', 'outline', 'outline-dash', 'soft'] as const;

    export type AlertVariant = (typeof alertVariants)[number];

    type AlertProps = {
        ref?: HTMLDivElement | null;
        color?: ColorVariant;
        variant?: AlertVariant;
        title?: string | Snippet;
        description?: string | Snippet;
        icon?: Snippet;
        children?: Snippet;
    } & Omit<HTMLAttributes<HTMLDivElement>, 'title'>;
</script>

<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    //     const alertVariants = tv({
    //     base: 'relative grid w-full grid-cols-[0_1fr] items-start gap-y-0.5 rounded-lg border px-4 py-3 text-sm has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current',
    //     variants: {
    //         variant: {
    //             default: 'bg-card text-card-foreground',
    //             destructive:
    //                 'text-destructive bg-card *:data-[slot=alert-description]:text-destructive/90 [&>svg]:text-current',
    //         },
    //     },
    //     defaultVariants: {
    //         variant: 'default',
    //     },
    // });

    // type AlertVariant = VariantProps<typeof alertVariants>['variant'];

    let {
        ref = $bindable(null),
        color = 'light',
        variant = 'solid',
        title,
        description,
        icon,
        children,
        class: _class,
        ...restProps
    }: AlertProps = $props();

    const colorVariants: Record<ColorVariant, string> = {
        primary: 'alert-primary',
        secondary: 'alert-secondary',
        accent: 'alert-accent',
        success: 'alert-success',
        info: 'alert-info',
        warning: 'alert-warning',
        error: 'alert-error',
        light: '',
        dark: 'alert-neutral',
    };

    const alertClass = $derived(
        cn(
            'alert gap-y-0.5 rounded-lg border bg-card px-4 py-3 text-sm text-card-foreground shadow-sm has-[>svg]:gap-x-3 [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current',
            colorVariants[color],
            variant === 'outline' ? 'alert-outline' : '',
            variant === 'outline-dash' ? 'alert-dash' : '',
            variant === 'soft' ? 'alert-soft' : '',
            _class
        )
    );
</script>

<div bind:this={ref} class={alertClass} data-slot="alert" role="alert" {...restProps}>
    {#if icon}
        {@render icon?.()}
    {/if}
    {#if title}
        <div
            class={cn(
                'line-clamp-1 min-h-4 font-medium tracking-tight text-current',
                icon && 'col-start-2'
            )}
            data-slot="alert-title">
            {#if typeof title === 'function'}
                {@render title?.()}
            {:else}
                {title}
            {/if}
        </div>
    {/if}
    {#if description}
        <div
            class={cn(
                'text-base-content/80 grid justify-items-start gap-1 text-sm [&_p]:leading-relaxed',
                icon && 'col-start-2'
            )}
            data-slot="alert-description">
            {#if typeof description === 'function'}
                {@render description?.()}
            {:else}
                {description}
            {/if}
        </div>
    {/if}
    {#if children}
        <div class={icon ? 'col-start-2' : ''}>
            {@render children?.()}
        </div>
    {/if}
</div>
