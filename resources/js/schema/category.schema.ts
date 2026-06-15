import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const categorySchema: DataSchema<App.Models.Category> = {
    name: {
        label: 'Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: { placeholder: 'Category name', autocorrect: 'off' },
        }),
    },
    color: {
        label: 'Color',
        form: () => ({
            type: 'text',
            name: 'color',
            inputProps: { placeholder: '#6366f1' },
        }),
    },
    is_fixed_cost: {
        label: 'Fixed cost',
        form: () => ({
            type: 'switch',
            name: 'is_fixed_cost',
        }),
    },
    // parent_id options are dynamic — extended in module component
};
