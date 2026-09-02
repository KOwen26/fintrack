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
        icon: 'solar--user-bold-duotone',
    },
    {
        value: AccountAccessType.Joint,
        label: 'Joint',
        description: 'Shared access',
        icon: 'solar--users-group-two-rounded-bold-duotone',
    },
];

export const iconForAccountType = (type: string): string =>
    ({
        [AccountType.DebitAccount]: 'banknote-2',
        [AccountType.CreditCard]: 'card',
        [AccountType.CashWallet]: 'money-bag',
        [AccountType.EWallet]: 'smartphone',
        [AccountType.Investment]: 'graph',
    })[type] ?? 'wallet';

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
    current_balance: {
        label: 'Current Balance',
        table: true,
        value: (data) => Formatter.currency(data.current_balance),
    },
    initial_balance: {
        label: 'Initial Balance',
        value: (data) => Number(data.initial_balance).toLocaleString('id-ID'),
        form: () => ({
            type: 'currency-input',
            name: 'initial_balance',
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
