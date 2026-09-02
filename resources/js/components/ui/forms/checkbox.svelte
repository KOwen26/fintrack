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
        data-slot="checkbox"
        class={cn(
            'peer flex size-4 shrink-0 items-center justify-center rounded-[4px] border border-input shadow-xs transition-shadow outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
            'data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:data-[state=checked]:bg-primary',
            className
        )}
        bind:ref
        bind:checked
        bind:indeterminate
        {...restProps}>
        {#snippet children({ checked, indeterminate })}
            <i
                data-slot="checkbox-indicator"
                class={[
                    'size-3 stroke-current transition-none',
                    checked
                        ? 'iconify tabler--check'
                        : indeterminate
                          ? 'iconify solar--minus-bold-duotone'
                          : undefined,
                ]}></i>
        {/snippet}
    </Checkbox.Root>
    <label class="hover:cursor-pointer" for={id}>{@render _children?.()}</label>
</div>
