import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const householdSchema: DataSchema<App.Models.Household> = {
    name: {
        label: 'Household name',
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'e.g. Kevin & Partner', autocorrect: 'off' },
        }),
    },
};

// Separate schema for the invite form (different endpoint, different shape)
export const householdInviteSchema: DataSchema<{ email: string }> = {
    email: {
        label: 'Partner email',
        form: () => ({
            type: 'email',
            name: 'email',
            required: true,
            inputProps: {
                placeholder: 'partner@example.com',
                autocorrect: 'off',
                autocapitalize: 'none',
            },
        }),
    },
};
