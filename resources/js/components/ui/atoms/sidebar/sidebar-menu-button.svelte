<script lang="ts" module>
    import type { VariantProps } from 'tailwind-variants';

    import { tv } from 'tailwind-variants';

    export const sidebarMenuButtonVariants = tv({
        base: [
            'peer/menu-button outline-hidden ring-sidebar-ring hover:bg-sidebar-accent hover:text-sidebar-accent-foreground active:bg-sidebar-accent active:text-sidebar-accent-foreground group-has-data-[sidebar=menu-action]/menu-item:pr-8 data-[active=true]:bg-sidebar-accent data-[active=true]:text-sidebar-accent-foreground data-[state=open]:hover:bg-sidebar-accent data-[state=open]:hover:text-sidebar-accent-foreground flex w-full items-center gap-2 overflow-hidden rounded-md p-2 text-left text-sm transition-[width,height,padding] focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 data-[active=true]:font-medium [&>span:last-child]:truncate ',
            '[&>svg]:size-5 [&>svg]:shrink-0',
            'group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-0! group-data-[collapsible=icon]:gap-0! group-data-[collapsible=icon]:[&>svg]:first:-ml-px',
        ],
        variants: {
            variant: {
                default: 'hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                outline:
                    'bg-background hover:bg-sidebar-accent hover:text-sidebar-accent-foreground shadow-[0_0_0_1px_var(--sidebar-border)] hover:shadow-[0_0_0_1px_var(--sidebar-accent)]',
            },
            size: {
                default: 'h-8 text-sm',
                sm: 'h-7 text-xs',
                lg: 'group-data-[collapsible=icon]:p-0! h-12 text-sm',
            },
        },
        defaultVariants: {
            variant: 'default',
            size: 'default',
        },
    });

    export type SidebarMenuButtonVariant = VariantProps<
        typeof sidebarMenuButtonVariants
    >['variant'];
    export type SidebarMenuButtonSize = VariantProps<typeof sidebarMenuButtonVariants>['size'];
</script>

<script lang="ts">
    import type { AnyRecord } from '@type/index';
    import type { WithElementRef, WithoutChildrenOrChild } from '@utilities/shadcn.js';
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { useSidebar } from './context.svelte.js';

    import { mergeProps, Tooltip as TooltipPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import Tooltip from '@components/ui/tooltip.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        child,
        variant = 'default',
        size = 'default',
        isActive = false,
        tooltipContent,
        tooltipContentProps,
        ...restProps
    }: WithElementRef<HTMLAttributes<HTMLButtonElement>, HTMLButtonElement> & {
        isActive?: boolean;
        variant?: SidebarMenuButtonVariant;
        size?: SidebarMenuButtonSize;
        tooltipContent?: Snippet | string;
        tooltipContentProps?: WithoutChildrenOrChild<TooltipPrimitive.ContentProps>;
        child?: Snippet<[{ props: Record<string, unknown> }]>;
    } = $props();

    const sidebar = useSidebar();

    const buttonProps = $derived({
        class: cn(sidebarMenuButtonVariants({ variant, size }), className),
        'data-slot': 'sidebar-menu-button',
        'data-sidebar': 'menu-button',
        'data-size': size,
        'data-active': isActive,
        ...restProps,
    });
</script>

{#if !tooltipContent}
    {@render Button({})}
{:else}
    <Tooltip
        align="center"
        hidden={sidebar.state !== 'collapsed' || sidebar.isMobile}
        side="right"
        {...tooltipContentProps}>
        {#snippet trigger(props)}
            {@render Button({ props })}
        {/snippet}
        {#if typeof tooltipContent === 'string'}
            {tooltipContent}
        {:else if tooltipContent}
            {@render tooltipContent()}
        {/if}
    </Tooltip>
{/if}

{#snippet Button({ props }: { props?: AnyRecord })}
    {@const mergedProps = mergeProps(buttonProps, props)}
    {#if child}
        {@render child({ props: mergedProps })}
    {:else}
        <button bind:this={ref} {...mergedProps}>
            {@render children?.()}
        </button>
    {/if}
{/snippet}
