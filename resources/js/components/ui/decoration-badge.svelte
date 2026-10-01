<script lang="ts" module>
    import type { RestProps } from '@type/index';

    /**
     * Square mark rendering an entity's decoration pair (icon + tinted
     * background), with a lettermark (initials) fallback when no decoration
     * icon is available. Not a DaisyUI `Badge` (content-hugging text pill).
     */
    export type DecorationBadgeSize = 'sm' | 'md' | 'lg';

    export interface DecorationBadgeItem {
        /** Iconify icon classes, e.g. `solar--tag-bold-duotone`. */
        icon?: string;
        /** Lettermark fallback (initials) rendered when no decoration icon is available. */
        text?: string;
        /** Direct CSS background of the badge square. */
        background?: string;
        /** Direct CSS color of the icon/text. */
        color?: string;
    }

    interface DecorationBadgeProps extends DecorationBadgeItem, RestProps {
        /** Classes for the badge square (size, radius). */
        class?: string;
        /** Classes for the inner icon element. */
        iconClass?: string;
        /** Size of the badge. */
        size?: DecorationBadgeSize;
    }
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn';

    let {
        icon,
        text,
        background = 'color-mix(in oklab, var(--color-base-content) 10%, transparent)',
        color = 'var(--color-base-content)',
        class: _class,
        size = 'md',
        iconClass = 'size-5',
        ...restProps
    }: DecorationBadgeProps = $props();

    const sizeClasses: Record<
        DecorationBadgeSize,
        {
            class: string;
            iconClass: string;
            textClass: string;
        }
    > = {
        sm: {
            class: 'size-8 rounded-md',
            iconClass: 'size-4',
            textClass: '',
        },
        md: {
            class: 'size-10 rounded-lg',
            iconClass: 'size-5',
            textClass: '',
        },
        lg: {
            class: 'size-12 rounded-xl',
            iconClass: 'size-6',
            textClass: '',
        },
    };
</script>

<div
    style:background
    style:color
    class={cn('flex shrink-0 items-center justify-center', sizeClasses[size].class, _class)}
    {...restProps}>
    {#if icon}
        <i class={['iconify ', sizeClasses[size].iconClass, iconClass, icon]}></i>
    {:else if text}
        <span class={['text-2xs font-semibold', sizeClasses[size].textClass]}>{text}</span>
    {/if}
</div>
