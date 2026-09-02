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

    <div class="join overflow-clip rounded-md border border-base-200">
        <Button
            class={cn('size-8 p-1', mode === 'list' ? 'btn-active' : '')}
            color="light"
            onclick={() => (mode = 'list')}
            variant="ghost">
            <i class="iconify size-6 solar--list-bold-duotone"></i>
        </Button>

        <Button
            class={cn('size-8 p-1', mode === 'grid' ? 'btn-active' : '')}
            color="light"
            onclick={() => (mode = 'grid')}
            variant="ghost">
            <i class="iconify size-6 tabler--layout-grid"></i>
        </Button>
    </div>
</div>
