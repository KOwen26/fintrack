import type { DataSchema } from '@lib/data-composer';
import type { CategoryData } from '@type/generated';
import type { App } from '@wayfinder/types';

import TransactionType from '@wayfinder/App/Enums/TransactionType';

import Formatter from '@utilities/formatter';

export const templateTypeOptions = [
    { value: TransactionType.Income, label: 'Income' },
    { value: TransactionType.Expense, label: 'Expense' },
    { value: TransactionType.Transfer, label: 'Transfer' },
];

export const labelForType = (type: App.Enums.TransactionType): string =>
    templateTypeOptions.find((o) => o.value === type)?.label ?? type;

/** One-line summary for list rows: "Expense · Dining Out" or "Transfer → BCA Savings". */
export const templateSubtitle = (
    template: App.Models.TransactionTemplate,
    categories: CategoryData[]
): string => {
    if (template.type === TransactionType.Transfer) {
        return `${labelForType(template.type)} → ${template.destination_account?.name ?? 'Pick destination'}`;
    }

    const category =
        categories.find((c) => c.id === template.category_id)?.name ?? template.category_id ?? '—';

    return `${labelForType(template.type)} · ${category}`;
};

export const templateSchema: DataSchema<App.Models.TransactionTemplate> = {
    name: { label: 'Name' },
    type: { label: 'Type', value: (data) => labelForType(data.type) },
    category_id: {
        label: 'Category',
        show: (data) => data.type !== TransactionType.Transfer,
        value: (data) => data.category_id ?? '—',
    },
    amount: { label: 'Amount', value: (data) => Formatter.currency(data.amount) },
    account_id: {
        label: 'Default Source Account',
        value: (data) => data.account?.name ?? '—',
    },
    destination_account_id: {
        label: 'Destination Account',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => data.destination_account?.name ?? '—',
    },
    fee_amount: {
        label: 'Transfer Fee',
        show: (data) => data.type === TransactionType.Transfer,
        value: (data) => (data.fee_amount !== null ? Formatter.currency(data.fee_amount) : '—'),
    },
    description: { label: 'Note', value: (data) => data.description ?? '—' },
};
