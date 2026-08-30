<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { cn } from '@utilities/shadcn.js';

    import Input from '@components/ui/forms/input.svelte';

    let {
        ref = $bindable(null),
        value = $bindable(''),
        class: className,
        ...restProps
    }: ComponentProps<typeof Input> = $props();
</script>

<Input
    data-slot="sidebar-input"
    class={cn('bg-background h-8 w-full shadow-none', className)}
    data-sidebar="input"
    bind:ref
    bind:value
    {...restProps} />
