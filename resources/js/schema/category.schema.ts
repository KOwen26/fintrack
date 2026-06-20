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
    'decorations.icon': {
        label: 'Icon',
        form: () => ({
            type: 'text',
            name: 'decorations.icon',
            required: true,
            inputProps: { placeholder: 'ph:tag', autocorrect: 'off' },
        }),
    },
    'decorations.color': {
        label: 'Color',
        form: () => ({
            type: 'text',
            name: 'decorations.color',
            required: true,
            inputProps: { placeholder: '#6366f1' },
        }),
    },
    type: {
        label: 'Category type',
        form: () => ({
            type: 'select',
            name: 'type',
            required: true,
            options: [
                { value: 'input', label: 'Input' },
                { value: 'output', label: 'Output' },
            ],
        }),
    },
    order: {
        label: 'Sort order',
        form: () => ({
            type: 'number',
            name: 'order',
            step: '0.001',
            min: '0.000',
            max: '0.999',
            required: true,
            inputProps: { placeholder: '0.100' },
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
