import type {
    Column,
    ColumnFiltersState,
    GlobalFilterTableState,
    PaginationState,
    RowData,
    RowSelectionState,
    SortingState,
    TableOptions,
    TableOptionsResolved,
    TableState,
    VisibilityState,
} from '@tanstack/table-core';
import type { ComponentProps } from 'svelte';

import { renderComponent } from './render-helper';

import {
    createTable,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
} from '@tanstack/table-core';
import axios from 'axios';
import { SvelteSet, SvelteURLSearchParams } from 'svelte/reactivity';

import DatatableRowAction from '@components/ui/tables/datatable-row-action.svelte';
import DatatableSortableTh from '@components/ui/tables/datatable-sortable-th.svelte';

export type DataTableStates = {
    pagination: PaginationState;
    sorting: SortingState;
    columnFilters: ColumnFiltersState;
    globalFilter: GlobalFilterTableState;
    columnVisibility: VisibilityState;
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

type DataTableOptions<T> = Partial<{
    row_id?: string;
    datatable_options?: Omit<TableOptions<T>, 'columns'>;
}>;

export class DataTable<TData extends RowData> {
    static readonly DEFAULT_PAGE_SIZES = [10, 20, 50, 100, 200];

    static readonly MONTH_YEAR_PAGE_SIZES = [12, 24, 36, 48, 60];

    readonly #_data = $state<TData[] | string>(undefined);

    #previous_states = $state<DataTableStates>(undefined);

    readonly is_api = $derived<boolean>(
        !Array.isArray(this.#_data) && typeof this.#_data === 'string'
    );

    is_loading = $state<boolean>(false);

    table = $state<ReturnType<typeof createSvelteTable<TData>>>(undefined);

    table_rows = $state<TData[]>([]);

    table_states = $state<DataTableStates>(DataTable.defaultStates());

    table_meta = $state<DataTableMeta>({
        total: 0,
        has_filter: false,
        filter_quantity: 0,
    });

    constructor(
        data: TData[] | string,
        columns: TableOptions<TData>['columns'],
        options?: DataTableOptions<TData>
    ) {
        this.#_data = data;

        this.initTable(columns, options ?? {});
    }

    static defaultStates(): DataTableStates {
        return {
            pagination: {
                pageIndex: 0,
                pageSize: 10,
            },
            sorting: undefined,
            columnFilters: undefined,
            globalFilter: null,
            columnVisibility: {},
            rowSelection: {},
        };
    }

    static sortableHeader<TData extends RowData>(props: { column: Column<TData>; title: string }) {
        return renderComponent(DatatableSortableTh, props);
    }

    static rowAction<TData extends RowData>(props: ComponentProps<typeof DatatableRowAction>) {
        return renderComponent(DatatableRowAction, props);
    }

    refresh() {
        this.fetchData();
    }

    reset({ states = false }: { states?: boolean }) {
        if (states) {
            this.table_states = DataTable.defaultStates();
        }

        this.fetchData();
    }

    private initTable(columns: TableOptions<TData>['columns'], options?: DataTableOptions<TData>) {
        const states = $derived(this.table_states);

        if (this.is_api) {
            $effect(() => {
                if (JSON.stringify(states) !== JSON.stringify(this.#previous_states)) {
                    void this.fetchData();
                    this.#previous_states = states;
                }
            });
        } else {
            void this.fetchData();
        }

        const pageCount = $derived(
            this.is_api
                ? Math.ceil(
                      (this.table_meta?.has_filter
                          ? this.table_meta?.filter_quantity
                          : this.table_meta?.total) / states?.pagination?.pageSize
                  )
                : undefined
        );

        const tableOptions: TableOptions<TData> = $derived({
            data: this.table_rows,
            state: {
                get pagination() {
                    return states.pagination;
                },
                get sorting() {
                    return states.sorting;
                },
                get columnVisibility() {
                    return states.columnVisibility;
                },
                get columnFilters() {
                    return states.columnFilters;
                },
                get globalFilter() {
                    return states.globalFilter;
                },
                get rowSelection() {
                    return states.rowSelection;
                },
            },
            columns,
            pageCount,

            getCoreRowModel: getCoreRowModel(),

            getSortedRowModel: getSortedRowModel(),
            getPaginationRowModel: getPaginationRowModel(),
            getFilteredRowModel: getFilteredRowModel(),
            globalFilterFn: 'includesString',

            manualPagination: this.is_api,
            manualSorting: this.is_api,
            manualFiltering: this.is_api,

            onPaginationChange: async (updater) => {
                states.pagination = this.updaterHelper(states.pagination, updater);

                if (this.is_api) this.fetchData();
            },
            onSortingChange: async (updater) => {
                states.sorting = this.updaterHelper(states.sorting, updater);

                if (this.is_api) this.fetchData();
            },
            onColumnFiltersChange: async (updater) => {
                states.columnFilters = this.updaterHelper(states.columnFilters, updater);

                if (this.is_api) this.fetchData();
            },
            onGlobalFilterChange: async (updater) => {
                states.globalFilter = this.updaterHelper(states.globalFilter, updater);

                if (this.is_api) this.fetchData();
            },

            onColumnVisibilityChange: (updater) => {
                states.columnVisibility = this.updaterHelper(states.columnVisibility, updater);
            },
            onRowSelectionChange: async (updater) => {
                states.rowSelection = this.updaterHelper(states.rowSelection, updater);
            },
            getRowId: options?.row_id ? (row) => row?.[options.row_id]?.toString() : undefined,

            ...options?.datatable_options,
        });

        if (this.is_api) {
            $effect.pre(() => {
                this.table = createSvelteTable(tableOptions);
            });
        } else {
            this.table = createSvelteTable(tableOptions);
        }
    }

    private fetchData() {
        const data = this.#_data;
        const states = this.table_states;

        if (Array.isArray(data) && data?.length > 0) {
            this.table_rows = data;

            return;
        }

        if (typeof data === 'string') {
            this.is_loading = true;

            const queryParamsProps = {
                rows_per_page: states.pagination?.pageSize,
                current_page: states.pagination?.pageIndex + 1,
                sort: states.sorting?.map(({ id, desc }) => ({
                    id,
                    direction: desc ? 'desc' : 'asc',
                })),
                filters: states.columnFilters,
            };

            const queryParams = new SvelteURLSearchParams(
                Object.entries(queryParamsProps)
                    .filter(([key, value]) => value != undefined)
                    .map(([key, value]) => [key, JSON.stringify(value)])
            );

            const url = `${data}?${queryParams.toString()}`;

            console.info('Fetch Datatable API', { url });

            axios
                .get<DataTableServerResponse<TData>>(url, {
                    headers: {
                        Accept: 'application/json',
                    },
                })
                .then((response) => {
                    return response.data;
                })
                .then((result) => {
                    this.table_rows = result.data;

                    this.table_meta.total = result?.meta?.total;
                    this.table_meta.has_filter = result?.meta?.has_filter;
                    this.table_meta.filter_quantity =
                        result?.meta?.filter_quantity ?? result.data.length;

                    return result.data;
                })
                .catch((error) => {
                    console.error('Fetch Datatable API Error: ', { error });

                    this.table_rows = [];
                })
                .finally(() => {
                    this.is_loading = false;
                });

            return;
        }
    }

    private updaterHelper(value, updater) {
        return typeof updater === 'function' ? updater(value) : updater;
    }
}

// ==================================================================
// Shadcn-Svelte TanStack Table Utilities
// ==================================================================

/**
 * Creates a reactive TanStack table object for Svelte.
 * @param options Table options to create the table with.
 * @returns A reactive table object.
 * @example
 * ```svelte
 * <script>
 *   const table = createSvelteTable({ ... })
 * </script>
 *
 * <table>
 *   <thead>
 *     {#each table.getHeaderGroups() as headerGroup}
 *       <tr>
 *         {#each headerGroup.headers as header}
 *           <th colspan={header.colSpan}>
 *         	   <FlexRender content={header.column.columnDef.header} context={header.getContext()} />
 *         	 </th>
 *         {/each}
 *       </tr>
 *     {/each}
 *   </thead>
 * 	 <!-- ... -->
 * </table>
 * ```
 */
export function createSvelteTable<TData extends RowData>(options: TableOptions<TData>) {
    const resolvedOptions: TableOptionsResolved<TData> = mergeObjects(
        {
            state: {},
            onStateChange() {},
            renderFallbackValue: null,
            mergeOptions: (
                defaultOptions: TableOptions<TData>,
                options: Partial<TableOptions<TData>>
            ) => {
                return mergeObjects(defaultOptions, options);
            },
        },
        options
    );

    const table = createTable(resolvedOptions);
    let state = $state<Partial<TableState>>(table.initialState);

    function updateOptions() {
        table.setOptions((prev) => {
            return mergeObjects(prev, options, {
                state: mergeObjects(state, options.state || {}),

                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                onStateChange: (updater: any) => {
                    if (updater instanceof Function) state = updater(state);
                    else state = mergeObjects(state, updater);

                    options.onStateChange?.(updater);
                },
            });
        });
    }

    updateOptions();

    $effect.pre(() => {
        updateOptions();
    });

    return table;
}

type MaybeThunk<T extends object> = T | (() => T | null | undefined);
type Intersection<T extends readonly unknown[]> = (T extends [infer H, ...infer R]
    ? H & Intersection<R>
    : unknown) & {};

/**
 * Lazily merges several objects (or thunks) while preserving
 * getter semantics from every source.
 *
 * Proxy-based to avoid known WebKit recursion issue.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export function mergeObjects<Sources extends readonly MaybeThunk<any>[]>(
    ...sources: Sources
): Intersection<{ [K in keyof Sources]: Sources[K] }> {
    const resolve = <T extends object>(src: MaybeThunk<T>): T | undefined =>
        typeof src === 'function' ? (src() ?? undefined) : src;

    const findSourceWithKey = (key: PropertyKey) => {
        for (let i = sources.length - 1; i >= 0; i--) {
            const obj = resolve(sources[i]);
            if (obj && key in obj) return obj;
        }

        return undefined;
    };

    return new Proxy(Object.create(null), {
        get(_, key) {
            const src = findSourceWithKey(key);

            return src?.[key as never];
        },

        has(_, key) {
            return !!findSourceWithKey(key);
        },

        ownKeys(): (string | symbol)[] {
            const all = new SvelteSet<string | symbol>();
            for (const s of sources) {
                const obj = resolve(s);
                if (obj) {
                    for (const k of Reflect.ownKeys(obj) as (string | symbol)[]) {
                        all.add(k);
                    }
                }
            }

            return [...all];
        },

        getOwnPropertyDescriptor(_, key) {
            const src = findSourceWithKey(key);
            if (!src) return undefined;

            return {
                configurable: true,
                enumerable: true,
                // eslint-disable-next-line @typescript-eslint/no-explicit-any
                value: (src as any)[key],
                writable: true,
            };
        },
    }) as Intersection<{ [K in keyof Sources]: Sources[K] }>;
}
