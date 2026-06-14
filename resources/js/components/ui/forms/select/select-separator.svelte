<script lang="ts">
    import { Separator } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let { ref = $bindable(null), class: className, ...restProps }: Separator.RootProps = $props();
</script>

<Separator.Root
    class={cn('bg-border pointer-events-none -mx-1 my-1 h-px', className)}
    data-slot="select-separator"
    bind:ref
    {...restProps} />
