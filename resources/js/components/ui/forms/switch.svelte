<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { SwitchRootProps, SwitchRootPropsWithoutHTML } from 'bits-ui';

    import { Switch as SwitchPrimitive } from 'bits-ui';

    export type SwitchProps = SwitchRootPropsWithoutHTML;
</script>

<script lang="ts">
    import type { WithoutChildren } from '@utilities/shadcn.js';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        checked = $bindable(false),
        children,
        ...restProps
    }: WithoutChildren<SwitchProps & SwitchRootProps> & RestProps = $props();
</script>

<div class="inline-flex items-center gap-1">
    <SwitchPrimitive.Root
        data-slot="switch"
        class={cn(
            'peer inline-flex h-[1.15rem] w-8 shrink-0 items-center rounded-full border border-transparent shadow-xs transition-all outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=unchecked]:bg-input dark:data-[state=unchecked]:bg-input/80',
            className
        )}
        bind:ref
        bind:checked
        {...restProps}>
        <SwitchPrimitive.Thumb
            data-slot="switch-thumb"
            class={cn(
                'pointer-events-none block size-4 rounded-full bg-background ring-0 transition-transform data-[state=checked]:translate-x-[calc(100%-2px)] data-[state=unchecked]:translate-x-0 dark:data-[state=checked]:bg-primary-foreground dark:data-[state=unchecked]:bg-foreground'
            )} />
    </SwitchPrimitive.Root>
    {@render children?.()}
</div>
