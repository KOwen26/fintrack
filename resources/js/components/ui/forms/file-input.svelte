<script lang="ts" module>
    import type { HTMLInputValidationOptions } from '@lib/form-helper.svelte';
    import type { RestProps } from '@type/index';

    export type FileInputProps = Pick<HTMLInputValidationOptions, 'accept' | 'multiple'> & {
        files?: FileList;
    };
</script>

<script lang="ts">
    import Input from './input.svelte';

    let { files = $bindable(), class: className, ...props }: FileInputProps & RestProps = $props();
</script>

<Input {...props} class={className} type="file" bind:files />
