<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { RadioGroupRootProps, RadioGroupRootPropsWithoutHTML } from 'bits-ui';

    export type RadioGroupProps = RadioGroupRootPropsWithoutHTML;
</script>

<script lang="ts">
    import type { WithoutChild } from '@utilities/shadcn.js';

    import { RadioGroup as RadioGroupPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        value = $bindable(''),
        ...restProps
    }: WithoutChild<RadioGroupProps & RadioGroupRootProps> & RestProps = $props();
</script>

<RadioGroupPrimitive.Root
    class={cn('grid gap-3', className)}
    data-slot="radio-group"
    bind:ref
    bind:value
    {...restProps} />
