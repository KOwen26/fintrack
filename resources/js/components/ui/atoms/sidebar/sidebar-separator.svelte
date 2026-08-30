<script lang="ts">
    import { Separator } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let { ref = $bindable(null), class: className, ...restProps }: Separator.RootProps = $props();
</script>

<Separator.Root
    data-slot="sidebar-separator"
    class={cn('bg-sidebar-border', className)}
    data-sidebar="separator"
    bind:ref
    {...restProps} />
