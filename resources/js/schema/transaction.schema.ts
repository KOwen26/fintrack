import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

export const transactionSchema: DataSchema<App.Models.Transaction> = {
    amount: {
        label: 'Amount',
        value: (data) =>
            Number(data.amount).toLocaleString('id-ID', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }),
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0.00' },
        }),
    },
    transaction_date: {
        label: 'Date',
        value: (data) =>
            data.transaction_date
                ? new Date(data.transaction_date as string).toLocaleDateString('id-ID', {
                      day: '2-digit',
                      month: 'short',
                      year: 'numeric',
                  })
                : '-',
        form: () => ({
            type: 'date',
            name: 'transaction_date',
            required: true,
            inputProps: { max: new Date().toISOString().split('T')[0] },
        }),
    },
    description: {
        label: 'Note',
        value: (data) => (data.description as string | null) ?? '-',
        form: () => ({
            type: 'text',
            name: 'description',
            inputProps: { placeholder: 'Optional note or memo', autocorrect: 'off' },
        }),
    },
    // category_id: dynamic options — extended in component
    // type: excluded — form component handles type selection + UI alias logic
};
