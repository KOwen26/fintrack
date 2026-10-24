<script lang="ts" module>
    import type { RestProps } from '@type/index';

    /**
     * Square mark rendering an entity's decoration pair (icon + tinted
     * background), with a lettermark fallback when no decoration icon is
     * available. Not a DaisyUI `Badge` (content-hugging text pill).
     */
    export type DecorationBadgeSize = 'sm' | 'md' | 'lg';

    interface DecorationBadgeProps extends RestProps {
        /**
         * Entity decoration slug pair — resolved internally through the
         * decoration tables. Lookups win over the direct `icon`,
         * `background` and `color` props, so call sites chaining further
         * fallbacks (type icons, type styling) pass those chains as the
         * direct props.
         */
        decorations?: { icon?: string | null; color?: string | null } | null;
        /**
         * Verbatim lettermark rendered when no decoration icon is available
         * — pass name initials (`StringHelper.getInitials(name)`) or a
         * literal mark (`ALL`). Ignored when an icon resolves.
         */
        label?: string;
        /** Direct iconify icon classes — fallback when the decoration lookup misses. */
        icon?: string;
        /** Direct CSS background of the badge square — fallback when the decoration lookup misses. */
        background?: string;
        /** Direct CSS color of the icon/text — fallback when the decoration lookup misses. */
        color?: string;
        /** Classes for the badge square (size, radius). */
        class?: string;
        /** Classes for the inner icon element. */
        iconClass?: string;
        /** Size of the badge. */
        size?: DecorationBadgeSize;
    }
</script>

<script lang="ts">
    import { getDecorationColor, getDecorationIcon } from '@data/decorations';

    import { cn } from '@utilities/shadcn';

    let {
        decorations,
        label,
        icon: iconFallback,
        background: backgroundFallback,
        color: colorFallback,
        class: _class,
        size = 'md',
        iconClass = 'size-5',
        ...restProps
    }: DecorationBadgeProps = $props();

    /* Decoration lookups win; the direct props are fallbacks so fallback-
       chain call sites keep their resolution order. */
    const icon = $derived(getDecorationIcon(decorations?.icon)?.value ?? iconFallback);
    const hex = $derived(getDecorationColor(decorations?.color)?.hex);

    const background = $derived(
        hex
            ? `${hex}20`
            : (backgroundFallback ??
                  'color-mix(in oklab, var(--color-base-content) 10%, transparent)')
    );
    const color = $derived(hex ?? colorFallback ?? 'var(--color-base-content)');

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
    data-slot="decoration-badge"
    style:background
    style:color
    class={cn('flex shrink-0 items-center justify-center', sizeClasses[size].class, _class)}
    {...restProps}>
    {#if icon}
        <i class={['iconify ', sizeClasses[size].iconClass, iconClass, icon]}></i>
    {:else if label}
        <span class={['text-2xs font-semibold', sizeClasses[size].textClass]}>{label}</span>
    {/if}
</div>
