declare namespace App.Data {
    export type HouseholdData = {
        id: number;
        name: string;
        members: Array<App.Data.HouseholdMemberData>;
    };

    export type HouseholdMemberData = {
        id: number;
        user_id: number;
        name: string;
        role: App.Enums.HouseholdMemberRole;
        joined_at: string | null;
    };

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

    export type HouseholdMemberRole = 'owner' | 'member';

    export type ProviderStatus = 'active' | 'inactive';

    export type ProviderType = 'bank' | 'digital_bank' | 'e_wallet' | 'credit_loan' | 'investment';

    export type TestEnum = 'word' | 'two_word' | 'space word' | 'Title Word';
}
