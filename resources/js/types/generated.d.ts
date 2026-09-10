export type CategorySpendingItemData = {
    name: string;
    color: string;
    icon: string;
    total: number;
    percentage: number;
    categoryId: number | null;
    parentId: number | null;
    parentName: string | null;
};

export type CategorySpendingReportData = {
    categories: ParentSpendingItemData[];
    period_total: number;
    from: string;
    to: string;
};

export type ChildSpendingItemData = {
    categoryId: number;
    name: string;
    color: string;
    icon: string;
    total: number;
    percentage: number;
};

export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;

export type CursorPaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        path: string;
        per_page: number;
        next_cursor: string | null;
        next_page_url: string | null;
        prev_cursor: string | null;
        prev_page_url: string | null;
    };
};

export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;

export type DataTablePayloadData = {
    has_sort: boolean;
    has_filters: boolean;
    rows_per_page: number | null;
    current_page: number | null;
    sort: Array<any> | null;
    filters: Array<any> | null;
};

export type DecorationData = {
    icon: string | null;
    color: string | null;
};

export type DecorationItemData = {
    id: string;
    value: string;
    text_color: string | null;
};

export type LengthAwarePaginator<TKey, TValue> = {
    data: TKey extends string ? Record<TKey, TValue> : TValue[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    meta: {
        total: number;
        current_page: number;
        first_page_url: string;
        from: number | null;
        last_page: number;
        last_page_url: string;
        next_page_url: string | null;
        path: string;
        per_page: number;
        prev_page_url: string | null;
        to: number | null;
    };
};

export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;

export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;

export type ParentSpendingItemData = {
    categoryId: number;
    name: string;
    color: string;
    icon: string;
    total: number;
    percentage: number;
    children: ChildSpendingItemData[];
};

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

export type TransactionListData = {
    id: number;
    type: App.Enums.TransactionType;
    amount: number;
    description: string | null;
    transaction_date: string;
    category_id: number | null;
    account_id: number;
    transfer_link_id: string | null;
    destination_account_id: number | null;
    related_transaction: App.Models.Transaction;
    account: App.Models.Account;
    category: App.Models.Category;
};

export type UserTestData = {
    name: string;
    age: number;
    is_married: boolean;
    hobbies: string[];
    address: string[];
};
