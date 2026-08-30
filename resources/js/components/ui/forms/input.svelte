<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { WithElementRef } from '@utilities/shadcn.js';
    import type { HTMLInputAttributes, HTMLInputTypeAttribute } from 'svelte/elements';

    export type InputType = Exclude<
        HTMLInputTypeAttribute,
        | 'file'
        | 'checkbox'
        | 'radio'
        | 'button'
        | 'submit'
        | 'reset'
        | 'image'
        | 'color'
        | 'month'
        | 'search'
        | 'week'
    >;
    // export type InputType = Exclude<HTMLInputTypeAttribute, 'file'>;

    export type InputProps = WithElementRef<{ type?: InputType } & HTMLInputAttributes>;
    // export type InputProps = WithElementRef<
    //     // { type: 'file'; files?: FileList } | { type?: InputType; files?: undefined }
    // >;
</script>

<script lang="ts">
    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        value = $bindable(),
        type,
        files = $bindable(),
        class: className,
        ...restProps
    }: InputProps & Omit<HTMLInputAttributes, 'type'> & RestProps = $props();
</script>

{#if type === 'file'}
    <input
        bind:this={ref}
        data-slot="input"
        class={cn(
            // 'selection:bg-primary dark:bg-input/30 selection:text-primary-foreground border-input ring-offset-background placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 pt-1.5 text-sm font-medium shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            // 'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
            // 'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
            'input w-full',
            className
        )}
        type="file"
        bind:files
        bind:value
        {...restProps} />
{:else}
    <input
        bind:this={ref}
        data-slot="input"
        class={cn(
            // 'border-input bg-background selection:bg-primary dark:bg-input/30 selection:text-primary-foreground ring-offset-background placeholder:text-muted-foreground flex h-9 w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
            // 'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
            // 'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
            'input w-full',
            className
        )}
        {type}
        bind:value
        {...restProps} />
{/if}
