<script lang="ts">
    import { Separator } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let { ref = $bindable(null), class: className, ...restProps }: Separator.RootProps = $props();
</script>

<Separator.Root
    class={cn('bg-sidebar-border', className)}
    data-sidebar="separator"
    data-slot="sidebar-separator"
    bind:ref
    {...restProps} />
