<script lang="ts" module>
    import type { RestProps } from '@type/index';
    import type { FlatpickrOptions } from 'svelte-flatpickr-plus';

    export type DateInputProps = {
        value: string | Date;
        options?: FlatpickrOptions;
    };
</script>

<script lang="ts">
    import svelte_fpp, { l10n } from 'svelte-flatpickr-plus';
    import { twMerge } from 'tailwind-merge';

    let altElement: HTMLInputElement;
    let { value = $bindable(), options = {}, ...props }: DateInputProps & RestProps = $props();

    const defaultOptions: FlatpickrOptions = {
        locale: l10n.id,
        disableMobile: true,
        clickOpens: true,
        altInput: true,
        altInputClass: twMerge('input', 'flatpickr-preview', props.class),
        ariaDateFormat: 'd F Y',
        altFormat: 'd F Y',
        dateFormat: 'Y-m-d',
        position: 'auto center',
        time_24hr: true,
        defaultDate: value,
    };

    let finalOptions = $derived<FlatpickrOptions>({ ...defaultOptions, ...options });
</script>

<input {...props} class={['input', props.class]} bind:value use:svelte_fpp={finalOptions} />
