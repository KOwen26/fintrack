<script lang="ts" module>
    import type { RestProps } from '@type/index';

    export type RadioProps = {
        group: string;
    };
</script>

<script lang="ts">
    import { twMerge } from 'tailwind-merge';

    let {
        group = $bindable(),
        children,
        class: className,
        ...props
    }: RadioProps & RestProps = $props();
</script>

<label class={twMerge('flex items-center gap-2.5 text-nowrap', className)}>
    <input
        {...props}
        class="size-4 shrink-0 cursor-pointer appearance-none rounded-full border border-input shadow-xs transition-shadow outline-none checked:border-primary checked:bg-primary checked:shadow-[inset_0_0_0_2.5px_var(--background)] focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
        type="radio"
        bind:group />
    {@render children?.()}
</label>
