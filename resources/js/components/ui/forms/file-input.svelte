<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { HTMLInputValidationOptions } from '@utilities/form-helper.svelte';

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
