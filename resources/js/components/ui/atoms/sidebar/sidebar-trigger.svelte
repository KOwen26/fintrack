<script lang="ts">
    import type { ComponentProps } from 'svelte';

    import { useSidebar } from './context.svelte.js';

    import { twMerge } from 'tailwind-merge';

    import { cn } from '@utilities/shadcn.js';

    import Button from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        onclick,
        ...restProps
    }: ComponentProps<typeof Button> & {
        onclick?: (e: MouseEvent) => void;
    } = $props();

    const sidebar = useSidebar();
</script>

<Button
    data-slot="sidebar-trigger"
    class={cn('size-10 p-1', className)}
    data-sidebar="trigger"
    onclick={(e) => {
        onclick?.(e);
        sidebar.toggle();
    }}
    size="icon"
    type="button"
    variant="ghost"
    {...restProps}>
    <i
        class={twMerge(
            'iconify size-5',
            sidebar.open ? 'solar--sidebar-linear' : 'solar--sidebar-minimalistic-linear'
        )}></i>
    <span class="sr-only">Toggle Sidebar</span>
</Button>
