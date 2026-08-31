<script lang="ts">
    import type { MaskedInputProps } from './masked-input.svelte';

    import MaskedInput from './masked-input.svelte';

    interface Props extends MaskedInputProps {
        value: string;
    }

    let { value = $bindable(), ...props }: Props = $props();
</script>

<MaskedInput maskPreset="currency" {...props} bind:value />
