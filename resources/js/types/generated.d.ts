export type CategoryData = {
    id: string;
    name: string;
    cashflow: App.Enums.Cashflow;
    decorations: Decoration;
    group: CategoryGroupData;
};

export type CategoryGroupData = {
    id: string;
    name: string;
    cashflow: App.Enums.Cashflow;
    decorations: Decoration;
};

export type CategorySpendingItemData = {
    category_id: string;
    group: string;
    name: string;
    color: string;
    icon: string;
    total: number;
    percentage: number;
};

export type CategorySpendingReportData = {
    categories: ParentSpendingItemData[];
    period_total: number;
    from: string;
    to: string;
};

export type ChildSpendingItemData = {
    category_id: string;
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

export type Decoration = {
    readonly icon: string | null;
    readonly color: string | null;
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
    group_id: string;
    name: string;
    color: string;
    icon: string;
    total: number;
    percentage: number;
    children: ChildSpendingItemData[];
};

export type TransactionData = {
    account_id: number;
    type: App.Enums.TransactionType;
    amount: number;
    transaction_date: string;
    category_id: App.Enums.Category | null;
    description: string | null;
    flow: App.Enums.Cashflow | null;
    transfer_id: number | null;
};

export type TransactionDetailData = {
    id: number;
    type: App.Enums.TransactionType;
    flow: App.Enums.Cashflow;
    transfer_id: number | null;
    amount: number;
    transaction_date: string;
    description: string | null;
    account_id: number | null;
    destination_account_id: number | null;
    category_id: App.Enums.Category | null;
    account: App.Models.Account;
    destination_account: App.Models.Account;
};

export type TransactionFormData = {
    id: number | null;
    type: App.Enums.TransactionType;
    amount: number | null;
    transaction_date: string;
    description: string | null;
    account_id: number | null;
    destination_account_id: number | null;
    category_id: App.Enums.Category | null;
    fee_amount: number | null;
    transfer_id: number | null;
};

export type TransactionListData = {
    id: number;
    type: App.Enums.TransactionType;
    flow: App.Enums.Cashflow;
    amount: number;
    description: string | null;
    transaction_date: string;
    category_id: App.Enums.Category | null;
    account_id: number;
    transfer_id: number | null;
    destination_account_id: number | null;
    related_transaction: App.Models.Transaction;
    account: App.Models.Account;
};

export type TransferData = {
    account_id: number;
    destination_account_id: number;
    amount: number;
    transaction_date: string;
    fee_amount: number | null;
    description: string | null;
};
