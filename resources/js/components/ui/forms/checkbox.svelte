<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { CheckboxRootProps, CheckboxRootPropsWithoutHTML } from 'bits-ui';
    import type { Snippet } from 'svelte';

    import { Checkbox } from 'bits-ui';

    export type CheckboxProps = CheckboxRootPropsWithoutHTML & {
        children?: Snippet;
    };
</script>

<script lang="ts">
    import type { WithoutChildren } from '@utilities/shadcn';

    import { cn } from '@utilities/shadcn';

    let {
        ref = $bindable(null),
        checked = $bindable(false),
        indeterminate = $bindable(false),
        class: className,
        children: _children,
        ...restProps
    }: WithoutChildren<CheckboxProps & CheckboxRootProps> & RestProps = $props();

    const uid = $props.id();
    const id = $derived(restProps?.id ?? uid);
</script>

<div class="flex w-fit items-center gap-3 hover:cursor-pointer">
    <Checkbox.Root
        {id}
        class={cn(
            'border-input dark:bg-input/30 focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive peer flex size-4 shrink-0 items-center justify-center rounded-[4px] border shadow-xs transition-shadow outline-none focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50',
            'data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary data-[state=checked]:border-primary',
            className
        )}
        data-slot="checkbox"
        bind:ref
        bind:checked
        bind:indeterminate
        {...restProps}>
        {#snippet children({ checked, indeterminate })}
            <i
                class={[
                    'size-3 stroke-current transition-none',
                    checked
                        ? 'iconify ph--check-bold'
                        : indeterminate
                          ? 'iconify ph--minus-bold'
                          : undefined,
                ]}
                data-slot="checkbox-indicator"></i>
        {/snippet}
    </Checkbox.Root>
    <label class="hover:cursor-pointer" for={id}>{@render _children?.()}</label>
</div>
