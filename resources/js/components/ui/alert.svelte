<script lang="ts" module>
    export const alertVariants = ['solid', 'outline', 'outline-dash', 'soft'] as const;

    export type AlertVariant = (typeof alertVariants)[number];

    /** shadcn-style alert base — grid so the optional icon gets its own column. */
    export const alertClasses =
        'relative grid w-full grid-cols-[auto_1fr] items-start gap-x-3 gap-y-0.5 rounded-lg border bg-card px-4 py-3 text-sm text-card-foreground shadow-sm [&>svg]:size-4 [&>svg]:translate-y-0.5 [&>svg]:text-current';
</script>

<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { cn } from '@utilities/shadcn.js';

    type AlertProps = {
        ref?: HTMLDivElement | null;
        color?: ColorVariant;
        variant?: AlertVariant;
        title?: string | Snippet;
        description?: string | Snippet;
        icon?: Snippet;
        children?: Snippet;
    } & Omit<HTMLAttributes<HTMLDivElement>, 'title'>;

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

    const solidColors: Record<ColorVariant, string> = {
        primary: 'border-primary bg-primary text-primary-foreground',
        secondary: 'border-secondary bg-secondary text-secondary-foreground',
        accent: 'border-accent bg-accent text-accent-foreground',
        success: 'border-success bg-success text-success-foreground',
        info: 'border-info bg-info text-info-foreground',
        warning: 'border-warning bg-warning text-warning-foreground',
        error: 'border-error bg-error text-error-foreground',
        light: 'border-base-200 bg-card text-card-foreground',
        dark: 'border-neutral bg-neutral text-neutral-content',
    };

    const outlineColors: Record<ColorVariant, string> = {
        primary: 'border-primary text-primary',
        secondary: 'border-secondary text-secondary',
        accent: 'border-accent text-accent',
        success: 'border-success text-success',
        info: 'border-info text-info',
        warning: 'border-warning text-warning',
        error: 'border-error text-error',
        light: 'border-base-300 text-base-content',
        dark: 'border-neutral text-neutral',
    };

    const softColors: Record<ColorVariant, string> = {
        primary: 'border-primary/10 bg-primary/10 text-primary',
        secondary: 'border-secondary/10 bg-secondary/10 text-secondary',
        accent: 'border-accent/10 bg-accent/10 text-accent',
        success: 'border-success/10 bg-success/10 text-success',
        info: 'border-info/10 bg-info/10 text-info',
        warning: 'border-warning/10 bg-warning/10 text-warning',
        error: 'border-error/10 bg-error/10 text-error',
        light: 'border-base-content/10 bg-base-content/10 text-base-content',
        dark: 'border-neutral/10 bg-neutral/10 text-neutral',
    };

    const variantColors: Record<AlertVariant, Record<ColorVariant, string>> = {
        solid: solidColors,
        outline: outlineColors,
        'outline-dash': outlineColors,
        soft: softColors,
    };

    const alertClass = $derived(
        cn(
            alertClasses,
            variantColors[variant][color],
            variant === 'outline-dash' ? 'border-dashed' : '',
            _class
        )
    );
</script>

<div bind:this={ref} data-slot="alert" class={alertClass} role="alert" {...restProps}>
    {#if icon}
        {@render icon?.()}
    {/if}
    {#if title}
        <div
            data-slot="alert-title"
            class={cn(
                'line-clamp-1 min-h-4 font-medium tracking-tight text-current',
                icon && 'col-start-2'
            )}>
            {#if typeof title === 'function'}
                {@render title?.()}
            {:else}
                {title}
            {/if}
        </div>
    {/if}
    {#if description}
        <div
            data-slot="alert-description"
            class={cn(
                'grid justify-items-start gap-1 text-sm text-base-content/80 [&_p]:leading-relaxed',
                icon && 'col-start-2'
            )}>
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
