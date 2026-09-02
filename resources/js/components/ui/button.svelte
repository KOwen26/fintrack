<script lang="ts" module>
    import { tv } from 'tailwind-variants';

    export const buttonVariants = ['solid', 'outline', 'ghost', 'soft', 'link'] as const;

    export type ButtonVariant = (typeof buttonVariants)[number];

    const colorVariants: Record<ColorVariant, string> = {
        primary: 'btn-primary',
        secondary: 'btn-secondary',
        accent: 'btn-accent',
        success: 'btn-success',
        info: 'btn-info',
        warning: 'btn-warning',
        error: 'btn-error',
        light: '',
        dark: 'btn-neutral',
    };

    const linkColorVariants: Record<ColorVariant, string> = {
        primary:
            'text-primary hover:text-[color-mix(in_oklab,_var(--color-primary)_100%,_#000_20%)]',
        secondary:
            'text-secondary hover:text-[color-mix(in_oklab,_var(--color-secondary)_100%,_#000_20%)]',
        accent: 'text-accent hover:text-[color-mix(in_oklab,_var(--color-accent)_100%,_#000_20%)]',
        success:
            'text-success hover:text-[color-mix(in_oklab,_var(--color-success)_100%,_#000_20%)]',
        info: 'text-info hover:text-[color-mix(in_oklab,_var(--color-info)_100%,_#000_20%)]',
        warning:
            'text-warning hover:text-[color-mix(in_oklab,_var(--color-warning)_100%,_#000_20%)]',
        error: 'text-error hover:text-[color-mix(in_oklab,_var(--color-error)_100%,_#000_20%)]',
        light: 'text-base-content/50 hover:text-[color-mix(in_oklab,_var(--color-base-content)_100%,_#000_20%)]',
        dark: 'text-neutral hover:text-[color-mix(in_oklab,_var(--color-dark)_100%,_#000_20%)]',
    };

    const softColorVariants: Record<ColorVariant, string> = {
        primary: '',
        secondary: '',
        accent: '',
        success: '',
        info: '',
        warning: '',
        error: '',
        light: '',
        dark: '',
    };

    const outlineColorVariants: Record<ColorVariant, string> = {
        primary: '',
        secondary: '',
        accent: '',
        success: '',
        info: '',
        warning: '',
        error: '',
        light: 'border-border',
        dark: '',
    };

    export const tvButtonVariants = tv({
        base: "focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive inline-flex shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium outline-none transition-all focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0",
        variants: {
            variant: {
                default: 'bg-primary text-primary-foreground shadow-xs hover:bg-primary/90',
                destructive:
                    'bg-destructive shadow-xs hover:bg-destructive/90 focus-visible:ring-destructive/20 dark:focus-visible:ring-destructive/40 dark:bg-destructive/60 text-white',
                outline:
                    'bg-background shadow-xs hover:bg-accent hover:text-accent-foreground dark:bg-input/30 dark:border-input dark:hover:bg-input/50 border',
                secondary: 'bg-secondary text-secondary-foreground shadow-xs hover:bg-secondary/80',
                ghost: 'hover:bg-accent hover:text-accent-foreground dark:hover:bg-accent/50',
                link: 'text-primary underline-offset-4 hover:underline',
            },
            size: {
                default: 'h-9 px-4 py-2 has-[>svg]:px-3',
                sm: 'h-8 gap-1.5 rounded-md px-3 has-[>svg]:px-2.5',
                lg: 'h-10 rounded-md px-6 has-[>svg]:px-4',
                icon: 'size-9',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    });

    export type ButtonProps = {
        color?: ColorVariant;
        variant?: ButtonVariant;
        href?: string;
        withoutInertia?: boolean;
        useRouter?: boolean | Parameters<typeof router.visit>[1];
        children?: string | Snippet;
    } & WithoutChildren<RestProps>;
</script>

<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { RestProps } from '@/types';
    import type { WithoutChildren } from '@utilities/shadcn';
    import type { Snippet } from 'svelte';
    import type { Attachment } from 'svelte/attachments';

    import { inertia, router } from '@inertiajs/svelte';

    import { cn } from '@utilities/shadcn';

    let {
        class: className,
        size = 'default',
        ref = $bindable(null),
        type = 'button',
        color = 'primary',
        variant = 'solid',
        href = undefined,
        withoutInertia = false,
        useRouter = false,
        disabled,
        children,
        ...props
    }: ButtonProps = $props();

    const isLink = $derived(href && href?.length);
    const useInertia = $derived(isLink && (withoutInertia || useRouter) ? undefined : inertia);

    const routerAttachment: Attachment = (element) => {
        element.addEventListener('click', (event) => {
            event.preventDefault();

            const routerConfig = typeof useRouter === 'boolean' ? {} : useRouter;

            router.visit(href, routerConfig);
        });

        return () => {
            element.removeEventListener('click', () => {});
        };
    };

    const buttonClass = $derived(
        cn(
            'btn',
            colorVariants[color],
            variant === 'outline' ? ['btn-outline', outlineColorVariants[color]] : '',
            variant === 'ghost' ? 'btn-ghost' : '',
            variant === 'link' ? ['btn-link', 'h-fit p-1', linkColorVariants[color]] : '',
            variant === 'soft' ? ['btn-soft', softColorVariants[color]] : '',
            className
        )
    );
</script>

{#if href}
    <a
        bind:this={ref}
        data-slot="button"
        class={buttonClass}
        {@attach useRouter && routerAttachment}
        aria-disabled={disabled}
        href={disabled ? undefined : href}
        role={disabled ? 'link' : undefined}
        tabindex={disabled ? -1 : undefined}
        use:useInertia
        {...props}>
        {#if typeof children === 'function'}
            {@render children?.()}
        {:else}
            {children}
        {/if}
    </a>
{:else}
    <button bind:this={ref} data-slot="button" class={buttonClass} {disabled} {type} {...props}>
        {#if typeof children === 'function'}
            {@render children?.()}
        {:else}
            {children}
        {/if}
    </button>
{/if}
