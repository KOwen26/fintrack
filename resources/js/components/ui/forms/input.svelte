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

    const focusClasses =
        'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]';
    const ariaInvalidClasses =
        'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive';

    /** shadcn-style classes for single-line inputs (the canonical Input look). */
    export const inputClasses = [
        'border-input bg-background selection:bg-primary dark:bg-input/30 selection:text-primary-foreground ring-offset-background placeholder:text-muted-foreground flex min-h-12 w-full min-w-0 rounded-md border px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:min-h-10 md:text-sm',
        focusClasses,
        ariaInvalidClasses,
    ];

    /** File-input variant — transparent bg, tighter top padding, medium weight. */
    export const fileInputClasses = [
        'selection:bg-primary dark:bg-input/30 selection:text-primary-foreground border-input ring-offset-background placeholder:text-muted-foreground flex min-h-12 w-full min-w-0 rounded-md border bg-transparent px-3 pt-1.5 text-sm font-medium shadow-xs transition-[color,box-shadow] outline-none disabled:cursor-not-allowed disabled:opacity-50 md:min-h-10',
        focusClasses,
        ariaInvalidClasses,
    ];

    /** shadcn-style classes for a joined input group — one bordered box, borderless children. */
    export const inputGroupClasses =
        'border-input dark:bg-input/30 flex min-h-12 w-full min-w-0 items-center overflow-clip rounded-md border bg-transparent text-base shadow-xs transition-[color,box-shadow] outline-none focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:min-h-10 md:text-sm';
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
        class={cn(fileInputClasses, className)}
        type="file"
        bind:files
        bind:value
        {...restProps} />
{:else}
    <input
        bind:this={ref}
        data-slot="input"
        class={cn(inputClasses, className)}
        {type}
        bind:value
        {...restProps} />
{/if}
