import type {
    Column,
    ColumnDef,
    ColumnFiltersState,
    ColumnVisibilityState,
    PaginationState,
    RowData,
    RowSelectionState,
    SortingState,
    SvelteTable,
    TableOptions,
} from '@tanstack/svelte-table';
import type { ComponentProps } from 'svelte';

import { useHttp } from '@inertiajs/svelte';
import {
    columnFilteringFeature,
    columnVisibilityFeature,
    createFilteredRowModel,
    createPaginatedRowModel,
    createSortedRowModel,
    createTable,
    createTableState,
    globalFilteringFeature,
    metaHelper,
    renderComponent,
    rowPaginationFeature,
    rowSelectionFeature,
    rowSortingFeature,
    tableFeatures,
    tableOptions,
} from '@tanstack/svelte-table';
import { SvelteURLSearchParams } from 'svelte/reactivity';

import DatatableRowAction from '@components/ui/tables/datatable-row-action.svelte';
import DatatableSortableTh from '@components/ui/tables/datatable-sortable-th.svelte';

export interface DataTableColumnMeta {
    headerClass?: string;
    cellClass?: string;
    footerClass?: string;
}

/**
 * The feature set every `DataTable` instance is built with.
 *
 * Pass `AppTableFeatures` as the first generic of `ColumnDef` / `Column` when
 * defining columns for a  table.
 */
export const dataTableFeatures = tableFeatures({
    rowSortingFeature,
    rowPaginationFeature,
    rowSelectionFeature,
    columnFilteringFeature,
    columnVisibilityFeature,
    globalFilteringFeature,

    sortedRowModel: createSortedRowModel(),
    filteredRowModel: createFilteredRowModel(),
    paginatedRowModel: createPaginatedRowModel(),

    // filterFns: { includesString: filterFn_includesString },
    // sortFns: {
    //     alphanumeric: sortFn_alphanumeric,
    //     basic: sortFn_basic,
    //     datetime: sortFn_datetime,
    //     text: sortFn_text,
    // },

    columnMeta: metaHelper<DataTableColumnMeta>(),
});

export const defaultDatatableOptions = tableOptions({
    features: dataTableFeatures,
    globalFilterFn: 'auto',
});

export type AppTableFeatures = typeof dataTableFeatures;

export type DataTableStates = {
    pagination: PaginationState;
    sorting: SortingState;
    columnFilters: ColumnFiltersState;
    globalFilter: unknown;
    columnVisibility: ColumnVisibilityState;
    rowSelection: RowSelectionState;
};

export type DataTableMeta = {
    total: number;
    has_filter: boolean;
    filter_quantity: number;
};

export type DataTableServerResponse<T> = {
    data: T[];
    meta: DataTableMeta;
};

type DataTableOptions<TData extends RowData> = Partial<{
    row_id?: string;
    datatable_options?: Omit<
        TableOptions<AppTableFeatures, TData>,
        'features' | 'columns' | 'data' | 'state'
    >;
}>;

/**
 * TanStack Table  datatable orchestrator built on the official
 * `@tanstack/svelte-table` adapter.
 *
 * Accepts either an in-memory row array (client mode — sorting, filtering and
 * pagination run in the browser) or an API endpoint string (server mode —
 * `manualPagination` / `manualSorting` / `manualFiltering` are enabled and
 * state changes trigger a `useHttp` fetch expecting a
 * `DataTableServerResponse` shape).
 *
 * Must be instantiated during component initialization (top-level `<script>`)
 * — the underlying `createTable` registers a `$effect.pre` options sync.
 */
export class DataTable<TData extends RowData> {
    static readonly DEFAULT_PAGE_SIZES = [10, 20, 50, 100, 200];

    static readonly MONTH_YEAR_PAGE_SIZES = [12, 24, 36, 48, 60];

    readonly #source: TData[] | string;

    readonly #http: ReturnType<
        typeof useHttp<Record<string, never>, DataTableServerResponse<TData>>
    >;

    #previous_states = $state<string | undefined>(undefined);

    is_loading = $state<boolean>(false);

    readonly table: SvelteTable<AppTableFeatures, TData>;

    table_rows = $state.raw<TData[]>([]);

    table_meta = $state<DataTableMeta>({
        total: 0,
        has_filter: false,
        filter_quantity: 0,
    });

    readonly #pagination = createTableState<PaginationState>({ pageIndex: 0, pageSize: 10 });
    readonly #sorting = createTableState<SortingState>([]);
    readonly #column_filters = createTableState<ColumnFiltersState>([]);
    readonly #global_filter = createTableState<unknown>(null);
    readonly #column_visibility = createTableState<ColumnVisibilityState>({});
    readonly #row_selection = createTableState<RowSelectionState>({});

    /** Server mode — data is fetched from an endpoint instead of held in memory. */
    readonly is_api: boolean;

    constructor(
        data: TData[] | string,
        columns: ColumnDef<AppTableFeatures, TData>[],
        options?: DataTableOptions<TData>
    ) {
        this.#source = data;
        this.is_api = !Array.isArray(data) && typeof data === 'string';
        this.#http = useHttp<Record<string, never>, DataTableServerResponse<TData>>();

        const [getPagination, setPagination] = this.#pagination;
        const [getSorting, setSorting] = this.#sorting;
        const [getColumnFilters, setColumnFilters] = this.#column_filters;
        const [getGlobalFilter, setGlobalFilter] = this.#global_filter;
        const [getColumnVisibility, setColumnVisibility] = this.#column_visibility;
        const [getRowSelection, setRowSelection] = this.#row_selection;

        const rowId = options?.row_id;

        // Object-literal getters rebind `this` to the options object — alias the
        // class instance so reactive option getters read the table's $state.
        // eslint-disable-next-line @typescript-eslint/no-this-alias
        const self = this;

        this.table = createTable({
            ...options?.datatable_options,

            features: dataTableFeatures,
            columns,

            get data() {
                return self.table_rows;
            },

            get pageCount() {
                if (!self.is_api) return undefined;

                const quantity = self.table_meta.has_filter
                    ? self.table_meta.filter_quantity
                    : self.table_meta.total;

                return Math.ceil(quantity / getPagination().pageSize);
            },

            state: {
                get pagination() {
                    return getPagination();
                },
                get sorting() {
                    return getSorting();
                },
                get columnFilters() {
                    return getColumnFilters();
                },
                get globalFilter() {
                    return getGlobalFilter();
                },
                get columnVisibility() {
                    return getColumnVisibility();
                },
                get rowSelection() {
                    return getRowSelection();
                },
            },

            manualPagination: this.is_api,
            manualSorting: this.is_api,
            manualFiltering: this.is_api,

            globalFilterFn: 'auto',

            onPaginationChange: setPagination,
            onSortingChange: setSorting,
            // A filter change can shrink the result set below the current page —
            // reset to the first page so server mode never renders "No results"
            // for a page that no longer exists.
            onColumnFiltersChange: (updater) => {
                setColumnFilters(updater);
                setPagination((prev) => ({ ...prev, pageIndex: 0 }));
            },
            onGlobalFilterChange: (updater) => {
                setGlobalFilter(updater);
                setPagination((prev) => ({ ...prev, pageIndex: 0 }));
            },
            onColumnVisibilityChange: setColumnVisibility,
            onRowSelectionChange: setRowSelection,

            getRowId: rowId ? (row) => row?.[rowId]?.toString() : undefined,
        });

        if (this.is_api) {
            $effect(() => {
                const snapshot = JSON.stringify(this.table_states);

                if (snapshot !== this.#previous_states) {
                    this.#previous_states = snapshot;
                    void this.fetchData();
                }
            });
        } else {
            void this.fetchData();
        }
    }

    static defaultStates(): DataTableStates {
        return {
            pagination: {
                pageIndex: 0,
                pageSize: 10,
            },
            sorting: [],
            columnFilters: [],
            globalFilter: null,
            columnVisibility: {},
            rowSelection: {},
        };
    }

    static sortableHeader<TData extends RowData>(props: {
        column: Column<AppTableFeatures, TData>;
        title: string;
    }) {
        // renderComponent types generic components with their type params erased
        // to the constraint (Column<F, RowData>), but v9 declares Column's TData
        // as invariant — so Column<F, TData> cannot satisfy Column<F, RowData>
        // even though TData extends RowData. Runtime-safe handoff; remove this
        // suppression if the variance handling in @tanstack/svelte-table changes.
        // @ts-expect-error -- v9 Column TData invariance vs ComponentProps generic erasure
        return renderComponent(DatatableSortableTh, props);
    }

    static rowAction(props: ComponentProps<typeof DatatableRowAction>) {
        return renderComponent(DatatableRowAction, props);
    }

    get table_states(): DataTableStates {
        return {
            pagination: this.#pagination[0](),
            sorting: this.#sorting[0](),
            columnFilters: this.#column_filters[0](),
            globalFilter: this.#global_filter[0](),
            columnVisibility: this.#column_visibility[0](),
            rowSelection: this.#row_selection[0](),
        };
    }

    refresh() {
        this.fetchData();
    }

    reset({ states = false }: { states?: boolean } = {}) {
        if (states) {
            const defaults = DataTable.defaultStates();

            this.#pagination[1](defaults.pagination);
            this.#sorting[1](defaults.sorting);
            this.#column_filters[1](defaults.columnFilters);
            this.#global_filter[1](defaults.globalFilter);
            this.#column_visibility[1](defaults.columnVisibility);
            this.#row_selection[1](defaults.rowSelection);
        }

        this.fetchData();
    }

    private fetchData() {
        const data = this.#source;
        const states = this.table_states;

        if (Array.isArray(data) && data.length > 0) {
            this.table_rows = data;

            return;
        }

        if (typeof data !== 'string') return;

        this.is_loading = true;

        const queryParams = new SvelteURLSearchParams([
            ['rows_per_page', String(states.pagination?.pageSize)],
            ['current_page', String((states.pagination?.pageIndex ?? 0) + 1)],
        ]);

        if (states.sorting?.length) {
            queryParams.set(
                'sort',
                JSON.stringify(
                    states.sorting.map(({ id, desc }) => ({
                        id,
                        direction: desc ? 'desc' : 'asc',
                    }))
                )
            );
        }

        if (states.columnFilters?.length) {
            queryParams.set('filters', JSON.stringify(states.columnFilters));
        }

        const url = `${data}?${queryParams.toString()}`;

        console.info('Fetch Datatable API', { url });

        this.#http
            .get(url)
            .then((result) => {
                this.table_rows = result.data;

                this.table_meta.total = result?.meta?.total ?? result.data.length;
                this.table_meta.has_filter = result?.meta?.has_filter ?? false;
                this.table_meta.filter_quantity =
                    result?.meta?.filter_quantity ?? result.data.length;
            })
            .catch((error) => {
                console.error('Fetch Datatable API Error: ', { error });

                this.table_rows = [];
            })
            .finally(() => {
                this.is_loading = false;
            });
    }
}
