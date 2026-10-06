<script lang="ts">
    import type { ButtonVariant } from '@components/ui/button.svelte';

    import { RangeCalendar as RangeCalendarPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn.js';

    import { buttonClassVariants as buttonVariants } from '@components/ui/button.svelte';

    let {
        ref = $bindable(null),
        class: className,
        children,
        variant = 'ghost',
        ...restProps
    }: RangeCalendarPrimitive.PrevButtonProps & {
        variant?: ButtonVariant;
    } = $props();
</script>

<RangeCalendarPrimitive.PrevButton
    class={cn(
        buttonVariants({ variant, color: 'light' }),
        'size-(--cell-size) bg-transparent p-0 select-none disabled:opacity-50 rtl:rotate-180',
        className
    )}
    children={children || Fallback}
    bind:ref
    {...restProps} />

{#snippet Fallback()}
    <i class="iconify size-4 solar--alt-arrow-left-line-duotone"></i>
{/snippet}
