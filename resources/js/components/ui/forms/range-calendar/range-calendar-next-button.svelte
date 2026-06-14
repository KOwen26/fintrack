<script lang="ts">
    import type { ButtonVariant } from '@components/ui/button.svelte';

    import { RangeCalendar as RangeCalendarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { tvButtonVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        variant = 'ghost',
        ...restProps
    }: RangeCalendarPrimitive.NextButtonProps & {
        variant?: ButtonVariant;
    } = $props();
</script>

{#snippet Fallback()}
    <i class="iconify ph--caret-right-duotone size-4"></i>
{/snippet}

<RangeCalendarPrimitive.NextButton
    class={cn(
        buttonVariants({ variant }),
        'size-(--cell-size) bg-transparent p-0 select-none disabled:opacity-50 rtl:rotate-180',
        className
    )}
    children={children || Fallback}
    bind:ref
    {...restProps} />
