declare namespace App.Data {
    export type UserTestData = {
        name: string;
        age: number;
        is_married: boolean;
        hobbies: Array<string>;
        address: Array<string>;
    };
}
declare namespace App.Data.Transaction {
    export type TransactionDetailData = {
        id: number;
        type: App.Enums.TransactionType;
        amount: number;
        description: string | null;
        transaction_date: string;
        created_at: string | null;
        updated_at: string | null;
        account: App.Models.Account;
        category: App.Models.Category | null;
        creator: App.Models.User | null;
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
