<script lang="ts">
    import { THEME_OPTIONS, themeState } from '@hooks/use-theme.svelte';

    import Select from '@components/ui/forms/select.svelte';

    interface Props {
        class?: string;
    }

    let { class: _class }: Props = $props();

    const { theme, updateTheme } = themeState();

    const items = THEME_OPTIONS.map(({ value, label }) => ({ value, label }));

    const getSelected = (): string => theme.value;

    const setSelected = (value: string | string[] | number | number[]): void => {
        if (typeof value === 'string' && value) {
            updateTheme(value);
        }
    };
</script>

<div class={_class}>
    <Select clearable={false} {items} placeholder="Theme" bind:value={getSelected, setSelected} />
</div>
