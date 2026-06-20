import type { DataSchema } from '@utilities/data-composer';

import { App } from '@wayfinder/types';

import { DataComposer } from '@utilities/data-composer';

export type TransactionFormData = {
    type: string;
    amount: number;
    transaction_date: string;
    description: string;
    account_id: number;
    category_id?: number;
    destination_account_id?: number;
    fee_amount?: number | null;
};

const transactionSchema: DataSchema<
    App.Models.Transaction & { destination_account_id?: number; fee_amount?: number }
> = {
    category_id: {
        label: 'Category',
        form: () => ({
            type: 'select',
            options: [],
        }),
    },
    account_id: {
        label: 'Source Account',
        form: () => ({
            type: 'select',
            required: true,
            options: [],
        }),
    },
    destination_account_id: {
        label: 'Destination Account',
        form: () => ({
            type: 'select',
            required: true,
            options: [],
        }),
    },

    amount: {
        label: 'Amount',
        form: () => ({
            type: 'number',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0.00' },
        }),
    },
    transaction_date: {
        label: 'Date',
        form: () => ({
            type: 'date',
            required: true,
        }),
    },
    description: {
        label: 'Note',
        form: () => ({
            type: 'text',
            inputProps: { placeholder: 'Optional note or memo', autocorrect: 'off' },
        }),
    },
    fee_amount: {
        label: 'Transfer Fee (optional)',
        form: () => ({
            type: 'number',
            inputProps: { inputmode: 'decimal', min: 0.01, step: 0.01, placeholder: '0.00' },
        }),
    },
};

export const incomeSchema = DataComposer.from(transactionSchema)
    .except(['destination_account_id', 'fee_amount'])
    .getSchema();

export const expenseSchema = DataComposer.from(transactionSchema)
    .except(['destination_account_id', 'fee_amount'])
    .getSchema();

export const transferSchema = DataComposer.from(transactionSchema).getSchema();
