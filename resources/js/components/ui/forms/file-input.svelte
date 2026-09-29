<script lang="ts" module>
    import type { HTMLInputValidationOptions } from '@lib/form-helper.svelte';
    import type { RestProps } from '@type/index';

    export type FileInputProps = Pick<HTMLInputValidationOptions, 'accept' | 'multiple'> & {
        files?: FileList;
    };
</script>

<script lang="ts">
    let { files = $bindable(), ...props }: FileInputProps & RestProps = $props();
</script>

<label class="" for="">
    <input {...props} class="file-input" type="file" bind:files />
</label>
