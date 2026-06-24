<script lang="ts" module>
    import type { WithElementRef, WithoutChildren } from '@utilities/shadcn.js';
    import type { HTMLTextareaAttributes } from 'svelte/elements';

    export type TextareaProps = Record<string, any>;
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        value = $bindable(),
        class: className,
        ...restProps
    }: TextareaProps & WithoutChildren<WithElementRef<HTMLTextareaAttributes>> = $props();
</script>

<textarea
    bind:this={ref}
    data-slot="textarea"
    class={cn(
        // 'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive dark:bg-input/30 flex field-sizing-content min-h-16 w-full rounded-md border bg-transparent px-3 py-2 text-base shadow-xs transition-[color,box-shadow] outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
        'textarea w-full',
        className
    )}
    rows="3"
    bind:value
    {...restProps}></textarea>
