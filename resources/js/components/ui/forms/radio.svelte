<script lang="ts" module>
    import type { RestProps } from '@type/index';

    export type RadioProps = {
        group: string;
    };
</script>

<script lang="ts">
    import { twMerge } from 'tailwind-merge';

    let { group = $bindable(), children, ...props }: RadioProps & RestProps = $props();
</script>

<label class={twMerge('flex items-center gap-2.5 text-nowrap', props.class)}>
    <input {...props} class="radio" type="radio" bind:group />
    {@render children?.()}
</label>
