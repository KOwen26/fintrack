<script lang="ts">
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    type Size = 'sm' | 'md' | 'lg';

    interface Props extends RestProps {
        /** Iconify icon classes, e.g. `solar--tag-bold-duotone`. */
        icon?: string;
        /** Text fallback rendered when `icon` is not provided. */
        text?: string;
        /** Direct CSS background of the badge square. */
        background?: string;
        /** Direct CSS color of the icon/text. */
        color?: string;
        /** Classes for the badge square (size, radius). */
        class?: string;
        /** Classes for the inner icon element. */
        iconClass?: string;
        /** Size of the badge. */
        size?: Size;
    }

    let {
        icon,
        text,
        background,
        color,
        class: _class,
        size = 'md',
        iconClass = 'size-5',
        ...restProps
    }: Props = $props();

    const sizeClasses: Record<
        Size,
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
