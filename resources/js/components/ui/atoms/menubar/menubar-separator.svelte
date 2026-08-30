<script lang="ts">
    import { Menubar as MenubarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: MenubarPrimitive.SeparatorProps = $props();
</script>

<MenubarPrimitive.Separator
    data-slot="menubar-separator"
    class={cn('bg-border -mx-1 my-1 h-px', className)}
    bind:ref
    {...restProps} />
