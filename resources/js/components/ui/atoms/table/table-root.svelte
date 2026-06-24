<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLTableAttributes } from 'svelte/elements';

    import { twMerge } from 'tailwind-merge';

    import { cn } from '@utilities/shadcn.js';

    interface TableProps extends Omit<RestProps, 'children'>, WithElementRef<HTMLTableAttributes> {
        wrapperClass?: string;
    }

    let {
        ref = $bindable(null),
        wrapperClass = '',
        class: _class,
        children,
        ...restProps
    }: TableProps = $props();
</script>

<div data-slot="table-container" class={twMerge('relative w-full overflow-x-auto', wrapperClass)}>
    <table
        bind:this={ref}
        data-slot="table"
        class={cn('w-full caption-bottom text-sm', _class)}
        {...restProps}>
        {@render children?.()}
    </table>
</div>
