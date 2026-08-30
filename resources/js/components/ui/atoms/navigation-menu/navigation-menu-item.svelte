<script lang="ts">
    import { NavigationMenu as NavigationMenuPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        ...restProps
    }: NavigationMenuPrimitive.ItemProps = $props();
</script>

<NavigationMenuPrimitive.Item
    data-slot="navigation-menu-item"
    class={cn('relative', className)}
    bind:ref
    {...restProps} />
