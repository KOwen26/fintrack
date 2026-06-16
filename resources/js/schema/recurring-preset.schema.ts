import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import RecurringFrequency from '@wayfinder/App/Enums/RecurringFrequency';
import TransactionPresetType from '@wayfinder/App/Enums/TransactionPresetType';

export const recurringPresetSchema: DataSchema<App.Models.TransactionRecurringPreset> = {
    name: {
        label: 'Rule Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: {
                placeholder: 'e.g. Monthly Rent',
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
            ],
        }),
    },
    frequency: {
        label: 'Frequency',
        table: true,
        form: () => ({
            type: 'select',
            name: 'frequency',
            required: true,
            options: [
                { value: RecurringFrequency.Daily, label: 'Daily' },
                { value: RecurringFrequency.Weekly, label: 'Weekly' },
                { value: RecurringFrequency.Fortnightly, label: 'Fortnightly' },
                { value: RecurringFrequency.Monthly, label: 'Monthly' },
                { value: RecurringFrequency.Yearly, label: 'Yearly' },
            ],
        }),
    },
    amount: {
        label: 'Amount',
        value: (data) => Number(data.amount).toLocaleString('id-ID'),
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0' },
        }),
    },
    description: {
        label: 'Description',
        form: () => ({
            type: 'text',
            name: 'description',
            inputProps: { placeholder: 'Optional note', autocorrect: 'off' },
        }),
    },
    next_run_date: {
        label: 'First Run Date',
        value: (data) => new Date(data.next_run_date).toLocaleDateString('id-ID'),
        form: () => ({
            type: 'date',
            name: 'next_run_date',
            required: true,
        }),
    },
    recurrence_end_date: {
        label: 'End Date',
        value: (data) =>
            data.recurrence_end_date
                ? new Date(data.recurrence_end_date).toLocaleDateString('id-ID')
                : 'No end date',
        form: () => ({
            type: 'date',
            name: 'recurrence_end_date',
            inputProps: { placeholder: 'Leave blank for no end' },
        }),
    },
};
