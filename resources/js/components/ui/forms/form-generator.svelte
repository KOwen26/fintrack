<script lang="ts">
    import type { FormGeneratorProps } from '@utilities/form-helper.svelte';
    import type { ComponentProps } from 'svelte';

    import FieldInput from './field-input.svelte';
    import FormAction from './form-action.svelte';
    import Form from './form.svelte';

    import { useForm } from '@inertiajs/svelte';
    import { cn } from 'tailwind-variants';

    interface FormGenerator extends Omit<ComponentProps<typeof Form>, 'form'> {
        form?: ReturnType<typeof useForm>;
        formSchema: {
            // form?: ReturnType<typeof useForm>;
            fields: Record<string, FormGeneratorProps>;
            data: Record<string, any>;
        };
        variant?: keyof typeof variantClass;
        withoutSubmit?: boolean;
    }

    const baseClass = 'grid gap-5';
    const variantClass = {
        default: { form: '', field: '' },
        'grid-2': { form: 'md:grid-cols-12', field: 'md:col-span-6' },
        'grid-3': { form: 'md:grid-cols-12', field: 'md:col-span-4' },
        'grid-4': { form: 'md:grid-cols-12', field: 'md:col-span-3' },
    };

    let {
        form = $bindable(),
        formSchema = $bindable(),
        variant = 'default',
        withoutSubmit = false,
        class: _class,
        ...rest
    }: FormGenerator = $props();

    const className = $derived(cn(baseClass, variantClass[variant].form, _class));

    const handleShowField = (logic?: boolean | ((form: any) => boolean), value?: any) => {
        if (!logic) return true;

        return typeof logic === 'function' ? logic(value) : logic;
    };

    const handleDisabledField = (field: FormGeneratorProps, value?: any) => {
        if (!field.disabled && !field.disabledFn) return undefined;

        return typeof field.disabledFn === 'function' ? field.disabledFn(value) : field.disabled;
    };

    if (!form && !formSchema)
        throw new Error('FormGenerator.svelte: form or formSchema prop is required');

    form = form ?? useForm({ ...formSchema.data });
</script>

<Form class={className} {form} {...rest}>
    {#each Object.entries(formSchema.fields ?? {}) as [key, field] (key)}
        {@const fieldProps = Object.assign(field, {
            fieldProps: {
                ...field?.fieldProps,
                required: field?.fieldProps?.required ?? false,
                class: cn(variantClass[variant].field, field?.fieldProps?.class),
            },
            disabled: handleDisabledField(field, form),
        })}

        {#if handleShowField(field?.show, form)}
            <FieldInput
                {...fieldProps}
                error={form.errors[field.name]}
                bind:value={form[field.name]} />
        {/if}
    {/each}

    {#if !withoutSubmit}
        <FormAction class="col-span-full" {form} />
    {/if}
</Form>
