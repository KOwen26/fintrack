import type { CheckboxProps } from '@components/ui/forms/checkbox.svelte';
import type { CurrencyInputProps } from '@components/ui/forms/currency-input.svelte';
import type { DateInputProps } from '@components/ui/forms/date-input.svelte';
import type { FieldProps } from '@components/ui/forms/field.svelte';
import type { FileInputProps } from '@components/ui/forms/file-input.svelte';
import type { InputProps } from '@components/ui/forms/input.svelte';
import type { MaskedInputProps } from '@components/ui/forms/masked-input.svelte';
import type { PasswordInputProps } from '@components/ui/forms/password-input.svelte';
import type { PhoneInputProps } from '@components/ui/forms/phone-input.svelte';
import type { RadioGroupItemProps } from '@components/ui/forms/radio-group-item.svelte';
import type { RadioGroupProps } from '@components/ui/forms/radio-group.svelte';
import type { SelectProps } from '@components/ui/forms/select.svelte';
import type { SwitchProps } from '@components/ui/forms/switch.svelte';
import type { TextareaProps } from '@components/ui/forms/textarea.svelte';
import type { AnyRecord, PickAndWrapOthers, WithoutValue } from '@type/index';
import type { Attachment } from 'svelte/attachments';

import { renderComponent, renderSnippet } from './render-helper';

/**
 * Generic HTML input validation attributes
 */
export type HTMLInputValidationOptions = {
    /** Textual constraints (text, email, password, url, tel, search) */
    minLength?: number;
    maxLength?: number;
    pattern?: string; // Regex pattern as a string

    /** Numeric and Date constraints (number, range, date, time, etc.) */
    min?: number | string; // string used for dates (e.g., "2025-01-01")
    max?: number | string;
    step?: number | 'any';

    /** File constraints */
    accept?: string; // e.g., ".pdf,image/*"
    multiple?: boolean;
};

interface BaseInputFieldProps extends Partial<
    PickAndWrapOthers<FieldProps, 'title', 'fieldProps'>
> {
    name?: string;
    required?: boolean;
    readonly?: boolean;
    disabled?: boolean;
    disabledFn?: (form: AnyRecord) => boolean;
    show?: boolean | ((form: AnyRecord) => boolean);
    default?: unknown;
    usePrecognition?: boolean;
    notes?: FieldNotes;
}

export type InputValueOption = {
    label: string;
    value: string | number;
    disabled?: boolean;
    description?: string;
}[];

type FieldNotes = string | string[];

interface TextInputFieldProps extends BaseInputFieldProps {
    type: 'text' | 'email' | 'input' | 'number';
    inputProps?: WithoutValue<InputProps>;
}

interface PasswordInputFieldProps extends BaseInputFieldProps {
    type: 'password-input';
    inputProps?: WithoutValue<PasswordInputProps>;
}

interface PhoneInputFieldProps extends BaseInputFieldProps {
    type: 'phone-input';
    inputProps?: WithoutValue<PhoneInputProps>;
}

interface CurrencyInputFieldProps extends BaseInputFieldProps {
    type: 'currency-input';
    inputProps?: WithoutValue<CurrencyInputProps>;
}

interface TextareaFieldProps extends BaseInputFieldProps {
    type: 'textarea';
    inputProps?: WithoutValue<TextareaProps>;
}

interface MaskedInputFieldProps extends BaseInputFieldProps {
    type: 'masked-input';
    inputProps?: WithoutValue<MaskedInputProps>;
}

interface DateInputFieldProps extends BaseInputFieldProps {
    type: 'date';
    inputProps?: WithoutValue<DateInputProps>;
}

interface FileInputFieldProps extends BaseInputFieldProps {
    type: 'file';
    inputProps?: WithoutValue<FileInputProps>;
}

interface SelectInputFieldProps extends BaseInputFieldProps {
    type: 'select';
    options: InputValueOption;
    inputProps?: WithoutValue<Omit<SelectProps, 'items'>>;
}

interface CheckboxInputFieldProps extends BaseInputFieldProps {
    type: 'checkbox';
    options: InputValueOption;
    inputProps?: WithoutValue<CheckboxProps>;
    inputItemProps?: CheckboxProps;
}

interface RadioInputFieldProps extends BaseInputFieldProps {
    type: 'radio';
    options: InputValueOption;
    inputProps?: WithoutValue<RadioGroupProps>;
    inputItemProps?: RadioGroupItemProps;
}

interface SwitchInputFieldProps extends BaseInputFieldProps {
    type: 'switch';
    inputProps?: WithoutValue<SwitchProps>;
}

interface RawInputFieldProps extends BaseInputFieldProps {
    type: 'raw';
    input: ReturnType<typeof renderComponent> | ReturnType<typeof renderSnippet>;
    inputProps?: AnyRecord;
}

interface CategorySelectInputFieldProps extends BaseInputFieldProps {
    type: 'category-select';
    categories: { id: number; name: string; children?: { id: number; name: string }[] }[];
    inputProps?: AnyRecord;
}

interface AccountSelectInputFieldProps extends BaseInputFieldProps {
    type: 'account-select';
    endpoint?: string;
    accounts?: { id: number; name: string }[];
    inputProps?: AnyRecord;
}

export type FormGeneratorProps =
    | TextInputFieldProps
    | PasswordInputFieldProps
    | PhoneInputFieldProps
    | CurrencyInputFieldProps
    | TextareaFieldProps
    | MaskedInputFieldProps
    | DateInputFieldProps
    | FileInputFieldProps
    | SelectInputFieldProps
    | CheckboxInputFieldProps
    | RadioInputFieldProps
    | SwitchInputFieldProps
    | RawInputFieldProps
    | CategorySelectInputFieldProps
    | AccountSelectInputFieldProps;

export type InputFieldType = FormGeneratorProps['type'];

class FormHelper {
    static precognitionValidation(form, name: string = ''): Attachment {
        return (element: HTMLInputElement) => {
            const _name = element?.name ?? name;

            element.onchange = () => {
                form.validate(_name);
            };

            return () => {
                removeEventListener('change', element.onchange);
            };
        };
    }
}

export default FormHelper;
