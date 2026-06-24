<script lang="ts" module>
    import type { ToggleVariants } from './toggle.svelte';

    import { getContext, setContext } from 'svelte';

    export function setToggleGroupCtx(props: ToggleVariants) {
        setContext('toggleGroup', props);
    }

    export function getToggleGroupCtx() {
        return getContext<ToggleVariants>('toggleGroup');
    }
</script>

<script lang="ts">
    import { ToggleGroup as ToggleGroupPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        value = $bindable(),
        class: className,
        size = 'default',
        variant = 'default',
        ...restProps
    }: ToggleGroupPrimitive.RootProps & ToggleVariants = $props();

    setToggleGroupCtx({
        variant,
        size,
    });
</script>

<!--
Discriminated Unions + Destructing (required for bindable) do not
get along, so we shut typescript up by casting `value` to `never`.
-->
<ToggleGroupPrimitive.Root
    data-slot="toggle-group"
    class={cn(
        'group/toggle-group flex w-fit items-center rounded-md data-[variant=outline]:shadow-xs',
        className
    )}
    data-size={size}
    data-variant={variant}
    bind:value={value as never}
    bind:ref
    {...restProps} />
