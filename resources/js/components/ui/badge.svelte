<script lang="ts" module>
    export const badgeVariants = ['solid', 'outline', 'outline-dash', 'soft'] as const;
    export const badgeShapes = ['square', 'rounded', 'pill'] as const;

    export type BadgeVariant = (typeof badgeVariants)[number];
    export type BadgeShape = (typeof badgeShapes)[number];

    export const badgeSizes = ['sm', 'default', 'lg'] as const;

    export type BadgeSize = (typeof badgeSizes)[number];

    /** shadcn-style badge base — structure only; metrics live in badgeSizeClasses. */
    export const badgeClasses =
        'focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive inline-flex w-fit shrink-0 items-center justify-center overflow-hidden whitespace-nowrap rounded-md border font-medium transition-[color,box-shadow] focus-visible:ring-[3px] [&>svg]:pointer-events-none [&>svg]:size-3';

    /** Density metrics per size — `default` preserves the DaisyUI-md look the app ships. */
    export const badgeSizeClasses: Record<BadgeSize, string> = {
        sm: 'gap-1.5 h-5 px-2 text-xs',
        default: 'gap-2 h-6 px-2.75 text-sm',
        lg: 'gap-2 h-7 px-3.5 text-base',
    };
</script>

<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn.js';

    type BadgeProps = {
        color?: ColorVariant;
        variant?: BadgeVariant;
        shape?: BadgeShape;
        size?: BadgeSize;
    } & RestProps;

    let {
        ref = $bindable(null),
        color = 'primary',
        variant = 'solid',
        shape = 'rounded',
        size = 'default',
        class: _class,
        children,
        ...props
    }: BadgeProps = $props();

    const shapesClass: Record<BadgeShape, string> = {
        square: 'rounded-none',
        rounded: 'rounded',
        pill: 'rounded-full',
    };

    const solidColors: Record<ColorVariant, string> = {
        primary: 'border-transparent bg-primary text-primary-foreground',
        secondary: 'border-transparent bg-secondary text-secondary-foreground',
        accent: 'border-transparent bg-accent text-accent-foreground',
        success: 'border-transparent bg-success text-success-foreground',
        info: 'border-transparent bg-info text-info-foreground',
        warning: 'border-transparent bg-warning text-warning-foreground',
        error: 'border-transparent bg-error text-error-foreground',
        light: 'border-base-200 bg-base-100 text-base-content',
        dark: 'border-transparent bg-neutral text-neutral-content',
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

    const variantColors: Record<BadgeVariant, Record<ColorVariant, string>> = {
        solid: solidColors,
        outline: outlineColors,
        'outline-dash': outlineColors,
        soft: softColors,
    };

    const badgeClass = $derived(
        cn(
            badgeClasses,
            badgeSizeClasses[size],
            shapesClass[shape],
            variantColors[variant][color],
            variant === 'outline-dash' ? 'border-dashed' : '',
            _class
        )
    );
</script>

<span bind:this={ref} data-slot="badge" class={badgeClass} {...props}>
    {@render children?.()}
</span>
