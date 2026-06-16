import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';

export const transactionPresetSchema: DataSchema<App.Models.TransactionPreset> = {
    name: {
        label: 'Template Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: {
                placeholder: 'e.g. Morning Coffee',
                autocorrect: 'off',
                autocomplete: 'off',
            },
        }),
    },
    type: {
        label: 'Type',
        table: true,
        form: () => ({
            type: 'radio',
            name: 'type',
            required: true,
            options: [
                { value: TransactionPresetType.Income, label: 'Income' },
                { value: TransactionPresetType.Expense, label: 'Expense' },
                { value: TransactionPresetType.Transfer, label: 'Transfer' },
            ],
        }),
    },
    default_amount: {
        label: 'Default Amount',
        value: (data) =>
            data.default_amount != null ? Number(data.default_amount).toLocaleString('id-ID') : '—',
        form: () => ({
            type: 'number',
            name: 'default_amount',
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01, placeholder: '0' },
        }),
    },
    default_description: {
        label: 'Default Description',
        form: () => ({
            type: 'text',
            name: 'default_description',
            inputProps: { placeholder: 'Optional note', autocorrect: 'off' },
        }),
    },
};
