<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { Menubar as MenubarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        inset,
        class: className,
        ...restProps
    }: ComponentProps<typeof MenubarPrimitive.GroupHeading> & {
        inset?: boolean;
    } = $props();
</script>

<MenubarPrimitive.GroupHeading
    class={cn('px-2 py-1.5 text-sm font-medium data-[inset]:pl-8', className)}
    data-inset={inset}
    data-slot="menubar-group-heading"
    bind:ref
    {...restProps} />
