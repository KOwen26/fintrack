<script lang="ts" module>
    import type { FieldProps as BaseFieldProps } from './field.svelte';
    import type { FormGeneratorProps } from '@utilities/form-helper.svelte';
    import type { Snippet } from 'svelte';

    export type FieldInputProps = FormGeneratorProps &
        Pick<BaseFieldProps, 'error'> & { value: any; children?: Snippet };
</script>

<script lang="ts">
    import FlexRender from '../flex-render.svelte';
    import AccountSelect from './account-select.svelte';
    import CategorySelect from './category-select.svelte';
    import CheckboxGroup from './checkbox-group.svelte';
    import Checkbox from './checkbox.svelte';
    import CurrencyInput from './currency-input.svelte';
    import DateInput from './date-input.svelte';
    import Field from './field.svelte';
    import FileInput from './file-input.svelte';
    import Input from './input.svelte';
    import MaskedInput from './masked-input.svelte';
    import PasswordInput from './password-input.svelte';
    import PhoneInput from './phone-input.svelte';
    import RadioGroupItem from './radio-group-item.svelte';
    import RadioGroup from './radio-group.svelte';
    import Select from './select.svelte';
    import Switch from './switch.svelte';
    import Textarea from './textarea.svelte';

    let { value = $bindable(), children, ...restProps }: FieldInputProps = $props();

    const fieldProps = $derived({
        title: restProps?.title,
        notes: restProps?.notes,
        required: restProps?.required,
        error: restProps?.error,
        ...restProps.fieldProps,
    });

    function mapInputProps<T extends FormGeneratorProps>(props: T) {
        return {
            name: props?.name,
            required: props?.required,
            readonly: props?.readonly,
            disabled: props?.disabled,
            ...(props?.inputProps ?? {}),
        } as Omit<T, 'type' | 'fieldProps' | 'inputProps'> & Partial<T['inputProps']>;
    }
</script>

<Field {...fieldProps}>
    {#if restProps.type === 'text' || restProps.type === 'email' || restProps.type === 'input' || restProps.type === 'number'}
        {@const inputProps = mapInputProps(restProps)}
        {@const type = inputProps?.type ?? restProps?.type ?? 'text'}

        <Input {type} bind:value {...inputProps} />
    {:else if restProps.type === 'password-input'}
        {@const inputProps = mapInputProps(restProps)}

        <PasswordInput bind:value {...inputProps} />
    {:else if restProps.type === 'phone-input'}
        {@const inputProps = mapInputProps(restProps)}

        <PhoneInput bind:phone={value} {...inputProps} />
    {:else if restProps.type === 'currency-input'}
        {@const inputProps = mapInputProps(restProps)}

        <CurrencyInput bind:value {...inputProps} />
    {:else if restProps.type === 'textarea'}
        {@const inputProps = mapInputProps(restProps)}

        <Textarea bind:value {...inputProps} />
    {:else if restProps.type === 'masked-input'}
        {@const inputProps = mapInputProps(restProps)}

        <MaskedInput bind:value {...inputProps} />
    {:else if restProps.type === 'date'}
        {@const inputProps = mapInputProps(restProps)}

        <DateInput bind:value {...inputProps} />
    {:else if restProps.type === 'file'}
        {@const inputProps = mapInputProps(restProps)}

        <FileInput bind:files={value} {...inputProps} />
    {:else if restProps.type === 'select'}
        {@const inputProps = mapInputProps(restProps)}

        <Select items={restProps.options} bind:value {...inputProps} />
    {:else if restProps.type === 'switch'}
        {@const inputProps = mapInputProps(restProps)}

        <Switch bind:checked={value} {...inputProps} />
    {:else if restProps.type === 'checkbox'}
        {@const inputProps = mapInputProps(restProps)}

        <CheckboxGroup bind:value {...inputProps}>
            {#each restProps.options as { label, value, disabled }, i (i)}
                <Checkbox {disabled} value={String(value)} {...restProps.inputItemProps}>
                    {label}
                </Checkbox>
            {/each}
        </CheckboxGroup>
    {:else if restProps.type === 'radio'}
        {@const inputProps = mapInputProps(restProps)}
        <RadioGroup bind:value {...inputProps}>
            {#each restProps.options as { label, value, disabled }, i (i)}
                <RadioGroupItem {disabled} value={String(value)} {...restProps.inputItemProps}>
                    {label}
                </RadioGroupItem>
            {/each}
        </RadioGroup>
    {:else if restProps.type === 'category-select'}
        {@const inputProps = mapInputProps(restProps)}

        <CategorySelect categories={restProps.categories} bind:value {...inputProps} />
    {:else if restProps.type === 'account-select'}
        {@const inputProps = mapInputProps(restProps)}

        <AccountSelect endpoint={restProps.endpoint} bind:value {...inputProps} />
    {:else if restProps.type === 'raw'}
        {@const content = typeof value === 'string' ? value : () => value}

        <FlexRender {content} context={value} />
    {:else}
        {@render children?.()}
    {/if}
</Field>
