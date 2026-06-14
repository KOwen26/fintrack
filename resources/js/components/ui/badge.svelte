<script lang="ts" module>
    export const badgeVariants = ['solid', 'outline', 'outline-dash', 'soft'] as const;
    export const badgeShapes = ['square', 'rounded', 'pill'] as const;

    export type BadgeVariant = (typeof badgeVariants)[number];
    export type BadgeShape = (typeof badgeShapes)[number];

    //     export const badgeVariants = tv({
    //     base: 'focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive inline-flex w-fit shrink-0 items-center justify-center gap-1 overflow-hidden whitespace-nowrap rounded-md border px-2 py-0.5 text-xs font-medium transition-[color,box-shadow] focus-visible:ring-[3px] [&>svg]:pointer-events-none [&>svg]:size-3',
    //     variants: {
    //         variant: {
    //             default:
    //                 'bg-primary text-primary-foreground [a&]:hover:bg-primary/90 border-transparent',
    //             secondary:
    //                 'bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/90 border-transparent',
    //             destructive:
    //                 'bg-destructive [a&]:hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/70 border-transparent text-white',
    //             outline: 'text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground',
    //         },
    //     },
    //     defaultVariants: {
    //         variant: 'default',
    //     },
    // });

    type BadgeProps = {
        color?: ColorVariant;
        variant?: BadgeVariant;
        shape?: BadgeShape;
    } & RestProps;
</script>

<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { RestProps } from '@type/index';

    import { twMerge } from 'tailwind-merge';

    let {
        ref = $bindable(null),
        color = 'light',
        variant = 'solid',
        shape = 'rounded',
        class: className,
        children,
        ...props
    }: BadgeProps = $props();

    const shapesClass: Record<BadgeShape, string> = {
        square: 'rounded-none',
        rounded: 'rounded',
        pill: 'rounded-full',
    };

    const colorVariants: Record<ColorVariant, string> = {
        primary: 'badge-primary',
        secondary: 'badge-secondary',
        accent: 'badge-accent',
        success: 'badge-success',
        info: 'badge-info',
        warning: 'badge-warning',
        error: 'badge-error',
        light: '',
        dark: 'badge-neutral',
    };

    const badgeClass = $derived(
        twMerge(
            'badge',
            colorVariants[color],
            variant === 'outline' ? 'badge-outline' : '',
            variant === 'outline-dash' ? 'badge-dash' : '',
            variant === 'soft' ? 'badge-soft' : '',
            shapesClass[shape],
            props.class
        )
    );
</script>

<span bind:this={ref} class={badgeClass} data-slot="badge" {...props}>
    {@render children?.()}
</span>
