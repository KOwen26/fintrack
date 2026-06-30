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
        description: string;
        transaction_date: string;
        created_at: string;
        updated_at: string;
        account: App.Models.Account;
        category: App.Models.Category;
        creator: App.Models.User;
    };
}
