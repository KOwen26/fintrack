<script lang="ts">
    import { THEME_OPTIONS, themeState } from '@lib/theme-handler.svelte';

    import Select from '@components/ui/forms/select.svelte';

    interface Props {
        class?: string;
    }

    let { class: _class }: Props = $props();

    const { theme, updateTheme } = themeState();

    const options = THEME_OPTIONS.map(({ value, label }) => ({ value, label }));

    const getSelected = (): string => theme.value;

    const setSelected = (value: string): void => {
        if (value) {
            updateTheme(value);
        }
    };
</script>

<div class={_class}>
    <Select {options} placeholder="Theme" bind:value={getSelected, setSelected} />
</div>
