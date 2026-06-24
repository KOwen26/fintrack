<script lang="ts">
    import { Drawer as DrawerPrimitive } from 'vaul-svelte';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: DrawerPrimitive.TitleProps = $props();
</script>

<DrawerPrimitive.Title
    data-slot="drawer-title"
    class={cn('text-foreground font-semibold', className)}
    bind:ref
    {...restProps} />
