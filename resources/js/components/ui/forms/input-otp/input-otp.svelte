<script lang="ts">
    import { PinInput as InputOTPPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    let {
        ref = $bindable(null),
        class: className,
        value = $bindable(''),
        ...restProps
    }: InputOTPPrimitive.RootProps = $props();
</script>

<InputOTPPrimitive.Root
    data-slot="input-otp"
    class={cn(
        'flex items-center gap-2 has-disabled:opacity-50 [&_input]:disabled:cursor-not-allowed',
        className
    )}
    bind:ref
    bind:value
    {...restProps} />
