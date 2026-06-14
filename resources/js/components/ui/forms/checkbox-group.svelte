<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type {
        CheckboxGroupProps as BitsCheckboxGroupProps,
        CheckboxGroupPropsWithoutHTML,
    } from 'bits-ui';
    import type { Snippet } from 'svelte';

    import { Checkbox } from 'bits-ui';

    export type CheckboxGroupProps = CheckboxGroupPropsWithoutHTML & {
        children?: Snippet;
    };
</script>

<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn';

    import { cn } from '@utilities/shadcn';

    let {
        value = $bindable(),
        children,
        class: _class,
        ...props
    }: WithoutChild<CheckboxGroupProps & BitsCheckboxGroupProps> & RestProps = $props();
</script>

<Checkbox.Group class={cn('flex gap-3', _class)} bind:value {...props}>
    {@render children?.()}
</Checkbox.Group>
