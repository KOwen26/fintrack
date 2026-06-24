declare namespace App.Data {
    export type UserTestData = {
        name: string;
        age: number;
        is_married: boolean;
        hobbies: Array<string>;
        address: Array<string>;
    };
}
declare namespace App.Enums {
    export type AccountAccessType = 'personal' | 'joint';

    export type AccountType =
        | 'debit_account'
        | 'credit_card'
        | 'cash_wallet'
        | 'e_wallet'
        | 'investment';

    export type AlertLevel = 'normal' | 'warning' | 'high_risk';

    export type CategoryType = 'input' | 'output';

    export type ProviderStatus = 'active' | 'inactive';

    export type ProviderType = 'bank' | 'digital_bank' | 'e_wallet' | 'credit_loan' | 'investment';

    export type RecurringFrequency = 'daily' | 'weekly' | 'fortnightly' | 'monthly' | 'yearly';

    export type TestEnum = 'word' | 'two_word' | 'space word' | 'Title Word';

    export type TransactionType = 'income' | 'expense' | 'transfer_out' | 'transfer_in' | 'fee';
}
