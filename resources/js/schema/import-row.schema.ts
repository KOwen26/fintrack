import type { DataSchema } from '@lib/data-composer';
import type { App } from '@wayfinder/types';

import TransactionType from '@wayfinder/App/Enums/TransactionType';

export const importRowSchema: DataSchema<App.Models.ImportRow> = {
    transaction_date: {
        label: 'Date',
        form: () => ({
            type: 'date',
            name: 'transaction_date',
            required: true,
        }),
    },
    description: {
        label: 'Description',
        form: () => ({
            type: 'text',
            name: 'description',
            required: true,
            inputProps: { placeholder: 'e.g. Pembayaran QR ke Khas Solo 609015160267' },
        }),
    },
    amount: {
        label: 'Amount',
        form: () => ({
            type: 'number',
            name: 'amount',
            required: true,
            inputProps: { min: '0.01', step: 'any' },
        }),
    },
    transaction_type: {
        label: 'Type',
        form: () => ({
            type: 'select',
            name: 'transaction_type',
            required: true,
            options: [
                { label: 'Income', value: TransactionType.Income },
                { label: 'Expense', value: TransactionType.Expense },
                { label: 'Transfer', value: TransactionType.Transfer },
            ],
        }),
    },
    transfer_destination_account_id: {
        label: 'Transfer Destination',
        form: () => ({
            type: 'select',
            name: 'transfer_destination_account_id',
            required: true,
            show: (form) => form.transaction_type === TransactionType.Transfer,
        }),
    },
};
