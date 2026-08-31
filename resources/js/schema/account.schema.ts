import type { DataSchema } from '@utilities/data-composer';
import type { App } from '@wayfinder/types';

import AccountAccessType from '@wayfinder/App/Enums/AccountAccessType';
import AccountType from '@wayfinder/App/Enums/AccountType';

import DateTimeHelper from '@utilities/date-time-helper';
import Formatter from '@utilities/formatter';

// ── Static options — single source of truth for schema forms & custom pickers ──
export const accountTypeOptions = [
    { value: AccountType.DebitAccount, label: 'Debit', icon: 'solar--buildings-bold-duotone' },
    { value: AccountType.CreditCard, label: 'Credit', icon: 'solar--wallet-bold-duotone' },
    { value: AccountType.CashWallet, label: 'Cash', icon: 'solar--wallet-money-bold-duotone' },
    { value: AccountType.EWallet, label: 'E-Wallet', icon: 'solar--smartphone-bold-duotone' },
    { value: AccountType.Investment, label: 'Invest', icon: 'solar--chart-bold-duotone' },
];

export const accountAccessTypeOptions = [
    {
        value: AccountAccessType.Personal,
        label: 'Personal',
        description: 'Solo ownership',
        icon: 'ph--user-bold',
    },
    {
        value: AccountAccessType.Joint,
        label: 'Joint',
        description: 'Shared access',
        icon: 'ph--users-bold',
    },
];

export const iconForAccountType = (type: string): string =>
    ({
        [AccountType.DebitAccount]: 'bank-bold',
        [AccountType.CreditCard]: 'credit-card-bold',
        [AccountType.CashWallet]: 'money-bold',
        [AccountType.EWallet]: 'device-mobile-bold',
        [AccountType.Investment]: 'chart-line-bold',
    })[type] ?? 'wallet-bold';

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
    provider_id: {
        label: 'Provider',
        table: true,
        value: (data) => data.provider?.name ?? '—',
    },
    type: {
        label: 'Account Type',
        table: true,
        value: (data) => accountTypeOptions.find((o) => o.value === data.type)?.label ?? data.type,
        form: () => ({
            type: 'select',
            name: 'type',
            required: true,
            options: accountTypeOptions,
        }),
    },
    access_type: {
        label: 'Access',
        table: true,
        value: (data) =>
            accountAccessTypeOptions.find((o) => o.value === data.access_type)?.label ??
            data.access_type,
        form: () => ({
            type: 'radio',
            name: 'access_type',
            required: true,
            options: accountAccessTypeOptions,
        }),
    },
    currency: {
        label: 'Currency',
    },
    current_balance: {
        label: 'Current Balance',
        table: true,
        value: (data) => Formatter.currency(data.current_balance),
    },
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'),
        form: () => ({
            type: 'masked-input',
            name: 'initial_balance',
            inputProps: { maskPreset: 'currency' },
        }),
    },
    credit_card_limit: {
        label: 'Credit Limit',
        show: (data) => data.type === AccountType.CreditCard,
        value: (data) =>
            data.credit_card_limit ? Number(data.credit_card_limit).toLocaleString('id-ID') : '—',
        form: () => ({
            type: 'number',
            name: 'credit_card_limit',
            show: (form) => form.type === AccountType.CreditCard,
            inputProps: { inputmode: 'decimal', min: 0, step: 0.01 },
        }),
    },
    archived_at: {
        label: 'Archived At',
        value: (data) =>
            data.archived_at ? DateTimeHelper.format(data.archived_at, 'datetime') : '—',
    },
    created_at: {
        label: 'Created',
        table: true,
        value: (data) => (data.created_at ? DateTimeHelper.format(data.created_at, 'date') : '—'),
    },
};
