import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
import AccountType from '@wayfinder/App/Enums/AccountType';

export const accountSchema: DataSchema<App.Models.Account> = {
    name: {
        label: 'Name',
        table: true,
        form: () => ({
            type: 'text',
            name: 'name',
            required: true,
            inputProps: {
                placeholder: 'e.g. BCA Savings',
                autocorrect: 'off',
                autocomplete: 'off',
            },
        }),
    },
    type: {
        label: 'Account Type',
        table: true,
        form: () => ({
            type: 'select',
            name: 'type',
            required: true,
            options: [
                { value: AccountType.DebitAccount, label: 'Debit / Savings' },
                { value: AccountType.CreditCard, label: 'Credit Card' },
                { value: AccountType.CashWallet, label: 'Cash Wallet' },
                { value: AccountType.EWallet, label: 'E-Wallet' },
                { value: AccountType.Investment, label: 'Investment' },
            ],
        }),
    },
    access_type: {
        label: 'Access',
        form: () => ({
            type: 'radio',
            name: 'access_type',
            required: true,
            options: [
                { value: AccountAccessType.Personal, label: 'Personal' },
                { value: AccountAccessType.Joint, label: 'Joint' },
            ],
        }),
    },
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'),
        form: () => ({
            type: 'number',
            name: 'initial_balance',
            required: true,
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
        }),
    },
    // credit_card_limit: {
    //     label: 'Credit Limit',
    //     show: (data) => data.type === AccountType.CreditCard,
    //     value: (data) =>
    //         data.credit_card_limit ? Number(data.credit_card_limit).toLocaleString('id-ID') : '-',
    //     form: () => ({
    //         type: 'number',
    //         name: 'credit_card_limit',
    //         show: (form: any) => form.type === AccountType.CreditCard,
    //         inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
    //     }),
    // },
};
