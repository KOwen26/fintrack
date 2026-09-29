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
    let {
        value = $bindable(),
        options = {},
        class: _class,
        ...props
    }: DateInputProps & RestProps = $props();

    const defaultOptions: FlatpickrOptions = $derived({
        locale: l10n.id,
        disableMobile: true,
        clickOpens: true,
        altInput: true,
        altInputClass: twMerge(
            'input flex min-h-12 w-full px-4 transition-colors focus-within:bg-base-content/5 focus-within:outline-none hover:bg-base-content/5',
            'flatpickr-preview',
            _class
        ),
        ariaDateFormat: 'd F Y',
        altFormat: 'd F Y',
        dateFormat: 'Y-m-d',
        position: 'auto center',
        time_24hr: true,
        defaultDate: value,
    });

    let finalOptions = $derived<FlatpickrOptions>({ ...defaultOptions, ...options });
</script>

<input
    {...props}
    class={[
        'input flex min-h-12 w-full px-4 transition-colors focus-within:bg-base-content/5 focus-within:outline-none hover:bg-base-content/5',
        props.class,
    ]}
    bind:value
    use:svelte_fpp={finalOptions} />
