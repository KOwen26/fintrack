<script lang="ts" module>
    import type { ButtonSize } from '../button.svelte';
    import type { RestProps } from '@type/index';

    export type SubmitButtonProps = {
        submitting: boolean;
        size?: ButtonSize;
    };
</script>

<script lang="ts">
    import Button from '../button.svelte';

    import { twMerge } from 'tailwind-merge';

    let {
        submitting,
        size = 'default',
        children,
        class: _class,
        ...props
    }: SubmitButtonProps & RestProps = $props();
</script>

<Button
    {size}
    class={twMerge('flex items-center gap-x-3', _class)}
    disabled={submitting}
    type="submit"
    {...props}>
    {#if submitting}
        <i class="iconify size-4 animate-spin solar--refresh-bold-duotone"></i>
    {/if}
    {#if !!children}
        {@render children?.()}
    {:else}
        Submit
    {/if}
</Button>
