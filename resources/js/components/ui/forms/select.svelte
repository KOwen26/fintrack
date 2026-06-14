<script lang="ts" module>
    import type { RestProps } from '@type/index';

    import { config } from 'svelecte';

    export type SvelecteProps = typeof config;

    export type SelectItem = {
        value: string | number;
        label: string;
        description?: string;
        $disabled?: boolean;
    };

    export interface SelectProps extends Partial<SvelecteProps> {
        items: Array<number | string | SelectItem>;
        value: string | string[] | number | number[];
    }
</script>

<script lang="ts">
    import Svelecte from 'svelecte';

    let {
        valueField = 'value',
        placeholder = 'Pilih',
        items,
        value = $bindable(),
        ...props
    }: SelectProps & RestProps = $props();
</script>

<Svelecte
    clearable
    labelField="label"
    options={items}
    {placeholder}
    {valueField}
    bind:value
    {...props} />

<style lang="css">
    :global(:root) {
        --sv-min-height: calc(var(--spacing) * 10);
        --sv-bg: var(--color-base-100);
        --sv-disabled-bg: #eee;

        --sv-border: 1px solid var(--input-color);
        --sv-border-radius: calc(var(--spacing) * 1);
        --sv-general-padding: calc(var(--spacing) * 1) calc(var(--spacing) * 3);
        --sv-control-bg: var(--sv-bg);

        --sv-item-wrap-padding: 3px 3px 3px 6px;
        --sv-item-selected-bg: #efefef;
        --sv-item-btn-color: #000;
        --sv-item-btn-color-hover: var(--color-primary); /* same as icon-color-hover */
        --sv-item-btn-bg: #efefef;
        --sv-item-btn-bg-hover: #ddd;

        --sv-icon-color: #bbb;
        --sv-icon-color-hover: var(--color-primary);
        --sv-icon-bg: transparent;
        --sv-icon-size: 20px;
        --sv-separator-bg: var(--input-color);
        --sv-btn-border: 0;
        --sv-placeholder-color: #ccccd6;

        --sv-dropdown-bg: var(--sv-bg);
        --sv-dropdown-offset: 1px;
        --sv-dropdown-border: 1px solid rgba(0, 0, 0, 0.15);
        --sv-dropdown-width: auto;
        --sv-dropdown-shadow: 0 6px 12px #0000002d;
        --sv-dropdown-height: 240px;
        --sv-dropdown-active-bg: #f2f5f8;
        --sv-dropdown-selected-bg: #ecf3f9;

        --sv-create-kbd-border: 1px solid #efefef;
        --sv-create-kbd-bg: #fff;
        --sv-create-disabled-bg: #fcbaba;
        --sv-loader-border: 2px solid var(--input-color);
    }
</style>
