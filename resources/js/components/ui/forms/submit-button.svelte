<script lang="ts" module>
    import type { RestProps } from '@type/index';

    export type SubmitButtonProps = {
        submitting: boolean;
    };
</script>

<script lang="ts">
    import Button from '../button.svelte';

    import { twMerge } from 'tailwind-merge';

    let { submitting, children, class: _class, ...props }: SubmitButtonProps & RestProps = $props();
</script>

<Button
    class={twMerge('flex items-center gap-x-3', _class)}
    disabled={submitting}
    type="submit"
    {...props}>
    {#if submitting}
        <span class="loading loading-spinner loading-sm"></span>
    {/if}
    {#if !!children}
        {@render children?.()}
    {:else}
        Submit
    {/if}
</Button>
