<script lang="ts" module>
    import type { RestProps } from '@/types';

    export const dockVariants = ['float', 'flat'] as const;
    export const dockPositions = ['fixed', 'sticky'] as const;

    export type DockVariant = (typeof dockVariants)[number];
    export type DockPosition = (typeof dockPositions)[number];

    type DockProps = {
        variant?: DockVariant;
        position?: DockPosition;
        contentClass?: string;
    } & RestProps;
</script>

<script lang="ts">
    import { tw } from '@utilities/helper.svelte';
    import { cn } from '@utilities/shadcn';

    let {
        ref = $bindable(null),
        variant = 'float',
        position = 'fixed',
        class: _class,
        contentClass,
        children,
        ...props
    }: DockProps = $props();

    const variantClass: Record<DockVariant, { dock: string; content: string }> = {
        flat: {
            dock: tw`bottom-0 border-t-[0.5px] border-base-content/5 bg-base-100 rounded-t-2xl`,
            content: tw`h-[calc(4rem+env(safe-area-inset-bottom))] px-2 pt-2 pb-[env(safe-area-inset-bottom)]`,
        },
        float: {
            dock: tw`bottom-[env(safe-area-inset-bottom)] p-4 overflow-clip`,
            content: tw`min-h-16 rounded-xl border border-base-content/10 bg-base-100 p-3`,
        },
    };

    const positionClasses: Record<DockPosition, string> = {
        fixed: tw`fixed inset-x-0`,
        sticky: tw`sticky`,
    };

    const dockClass = $derived(cn(positionClasses[position], variantClass[variant].dock, _class));

    const contentClasses = $derived(cn(variantClass[variant].content, contentClass));
</script>

<div bind:this={ref} data-slot="dock" class={cn('z-30 w-full', dockClass)} {...props}>
    <div data-slot="dock-content" class={contentClasses}>
        {@render children?.()}
    </div>
</div>
