import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const budgetSchema: DataSchema<App.Models.Budget> = {
    limit_amount: {
        label: 'Monthly Limit',
        value: (data) =>
            Number(data.limit_amount).toLocaleString('id-ID', {
                minimumFractionDigits: 0,
                maximumFractionDigits: 0,
            }),
        form: () => ({
            type: 'number',
            name: 'limit_amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 1000, placeholder: '0' },
        }),
    },
    // category_id, year, month: dynamic options — extended in component
};
