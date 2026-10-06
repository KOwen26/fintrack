<script lang="ts" module>
    import type { ColorVariant } from '@/data/theme';

    import { tv } from 'tailwind-variants';

    export const buttonVariants = ['solid', 'outline', 'ghost', 'soft', 'link'] as const;
    export const buttonSizes = ['sm', 'default', 'lg', 'icon', 'icon-sm'] as const;

    export type ButtonVariant = (typeof buttonVariants)[number];
    export type ButtonSize = (typeof buttonSizes)[number];

    /** Single source of Button styling: variant templates reference CSS vars,
        color entries define them — no color×variant enumeration needed.
        `light` inverts (surface bg ≠ content color), so it uses compounds. */
    export const buttonClassVariants = tv({
        base: "focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive cursor-pointer inline-flex shrink-0 items-center justify-center gap-2 rounded-md text-sm font-medium whitespace-nowrap outline-none transition-all select-none touch-manipulation active:translate-y-px focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&_svg:not([class*='size-'])]:size-4 [&_svg]:pointer-events-none [&_svg]:shrink-0",
        variants: {
            variant: {
                solid: 'border-transparent bg-(--btn-color) text-(--btn-color-fg) shadow-xs hover:bg-(--btn-color)/90',
                outline: 'border-(--btn-color) text-(--btn-color) hover:bg-(--btn-color)/10',
                soft: 'border-(--btn-color)/10 bg-(--btn-color)/10 text-(--btn-color) hover:bg-(--btn-color)/20',
                ghost: 'text-(--btn-color) hover:bg-(--btn-color)/10',
                link: 'text-(--btn-color) underline-offset-4 hover:text-(--btn-color)/80 hover:underline h-fit p-1',
            },
            color: {
                primary:
                    '[--btn-color:var(--color-primary)] [--btn-color-fg:var(--color-primary-foreground)]',
                secondary:
                    '[--btn-color:var(--color-secondary)] [--btn-color-fg:var(--color-secondary-foreground)]',
                accent: '[--btn-color:var(--color-accent)] [--btn-color-fg:var(--color-accent-foreground)]',
                success:
                    '[--btn-color:var(--color-success)] [--btn-color-fg:var(--color-success-foreground)]',
                info: '[--btn-color:var(--color-info)] [--btn-color-fg:var(--color-info-foreground)]',
                warning:
                    '[--btn-color:var(--color-warning)] [--btn-color-fg:var(--color-warning-foreground)]',
                error: '[--btn-color:var(--color-error)] [--btn-color-fg:var(--color-error-foreground)]',
                light: '',
                dark: '[--btn-color:var(--color-neutral)] [--btn-color-fg:var(--color-neutral-content)]',
            },
            size: {
                sm: 'h-8 gap-1.5 px-3 text-xs',
                default: 'h-10 px-4',
                lg: 'h-12 px-5 text-base',
                icon: 'size-10 p-0',
                'icon-sm': 'size-8 p-0',
            },
        },
        compoundVariants: [
            {
                variant: 'solid',
                color: 'light',
                class: 'border-transparent bg-base-200 text-base-content hover:bg-base-300',
            },
            {
                variant: 'outline',
                color: 'light',
                class: 'border-border text-base-content hover:bg-base-content/10',
            },
            {
                variant: 'soft',
                color: 'light',
                class: 'border-base-content/10 bg-base-content/10 text-base-content hover:bg-base-content/20',
            },
            {
                variant: 'ghost',
                color: 'light',
                class: 'text-base-content hover:bg-base-content/10',
            },
            {
                variant: 'link',
                color: 'light',
                class: 'text-base-content/70 hover:text-base-content',
            },
        ],
        defaultVariants: {
            variant: 'solid',
            color: 'primary',
            size: 'default',
        },
    });

    export type ButtonProps = {
        color?: ColorVariant;
        variant?: ButtonVariant;
        size?: ButtonSize;
        href?: string;
        withoutInertia?: boolean;
        useRouter?: boolean | Parameters<typeof router.visit>[1];
        children?: string | Snippet;
    } & WithoutChildren<RestProps>;
</script>

<script lang="ts">
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

    const buttonClass = $derived(cn(buttonClassVariants({ variant, color, size }), className));
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
