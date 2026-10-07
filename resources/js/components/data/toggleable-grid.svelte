<script lang="ts">
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    interface Props extends RestProps {
        mode: 'list' | 'grid';
    }

    let { mode = $bindable('list'), children, class: _class, ...props }: Props = $props();
</script>

<div class={cn('flex flex-row items-center justify-between gap-5', _class)} {...props}>
    <div>
        {@render children?.()}
    </div>

    <div class="flex overflow-clip rounded-md border border-base-200">
        <Button
            class={cn(mode === 'list' ? 'bg-base-content/10 text-primary' : '')}
            aria-pressed={mode === 'list'}
            color="light"
            onclick={() => (mode = 'list')}
            size="icon-sm"
            variant="ghost">
            <i class="iconify size-6 solar--list-bold-duotone"></i>
        </Button>

        <Button
            class={cn(mode === 'grid' ? 'bg-base-content/10 text-primary' : '')}
            aria-pressed={mode === 'grid'}
            color="light"
            onclick={() => (mode = 'grid')}
            size="icon-sm"
            variant="ghost">
            <i class="iconify size-6 tabler--layout-grid"></i>
        </Button>
    </div>
</div>
