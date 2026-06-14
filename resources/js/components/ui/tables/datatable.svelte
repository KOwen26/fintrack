<script generics="TData, TValue" lang="ts">
    import type { Snippet } from 'svelte';

    import TableBody from '../atoms/table/table-body.svelte';
    import TableCell from '../atoms/table/table-cell.svelte';
    import TableFooter from '../atoms/table/table-footer.svelte';
    import TableHead from '../atoms/table/table-head.svelte';
    import TableHeader from '../atoms/table/table-header.svelte';
    import TableRoot from '../atoms/table/table-root.svelte';
    import TableRow from '../atoms/table/table-row.svelte';
    import Button from '../button.svelte';
    import FlexRender from '../flex-render.svelte';
    import Input from '../forms/input.svelte';
    import { Select as SelectRoot } from '../forms/select';
    import SelectContent from '../forms/select/select-content.svelte';
    import SelectItem from '../forms/select/select-item.svelte';
    import SelectTrigger from '../forms/select/select-trigger.svelte';

    import { twMerge } from 'tailwind-merge';

    import { DataTable } from '@utilities/datatable.svelte';

    type TDataTable = DataTable<TData>;
    type SnippetProps = {
        table: TDataTable['table'];
    };

    type DataTableProps = {
        dataTable: TDataTable;
        pageSizes?: number[];

        filter?: Snippet<[SnippetProps]>;

        withTotal?: boolean;
        withPagination?: boolean;
        withFilter?: boolean;
        withGlobalFilter?: boolean;
    };

    let {
        dataTable,
        pageSizes = DataTable.DEFAULT_PAGE_SIZES,

        withTotal = true,
        withPagination = true,
        withFilter = true,
        withGlobalFilter = true,

        filter,
    }: DataTableProps = $props();

    const { table, table_meta, is_api } = $derived(dataTable);

    const hasFooter = $derived(
        table
            .getFooterGroups()
            ?.some((footerGroup) =>
                footerGroup.headers.some((header) => header.column.columnDef?.footer)
            )
    );
</script>

<div
    class={twMerge(
        'space-y-5 overflow-clip border-neutral-400 bg-white',
        withFilter || withPagination ? 'rounded border py-5' : ''
    )}>
    {#if withFilter}
        {@render Filter({ table })}
    {/if}

    <TableRoot
        wrapperClass={twMerge(
            'border-neutral-400',
            withFilter || withPagination ? 'border-y' : 'rounded-sm border'
        )}>
        <TableHeader>
            {#each table.getHeaderGroups() as headerGroup (headerGroup.id)}
                <TableRow>
                    {#each headerGroup.headers as header (header.id)}
                        <TableHead
                            class={[header.column.columnDef?.meta?.headerClass]}
                            colspan={header.colSpan}>
                            {#if !header.isPlaceholder}
                                <FlexRender
                                    content={header.column.columnDef.header}
                                    context={header.getContext()} />
                            {/if}
                        </TableHead>
                    {/each}
                </TableRow>
            {/each}
        </TableHeader>
        <TableBody>
            {#each table.getRowModel().rows as row (row.id)}
                <TableRow data-state={row.getIsSelected() && 'selected'}>
                    {#each row.getVisibleCells() as cell (cell.id)}
                        <TableCell class={[cell.column.columnDef?.meta?.cellClass]}>
                            <FlexRender
                                content={cell.column.columnDef.cell}
                                context={cell.getContext()} />
                        </TableCell>
                    {/each}
                </TableRow>
            {:else}
                <TableRow>
                    <TableCell class="h-24 text-center" colspan={table.getAllColumns().length}>
                        No results.
                    </TableCell>
                </TableRow>
            {/each}
        </TableBody>
        {#if hasFooter}
            <TableFooter>
                {#each table.getFooterGroups() as footerGroup (footerGroup.id)}
                    <TableRow>
                        {#each footerGroup.headers as footer (footer.id)}
                            <TableCell class={[footer.column.columnDef?.meta?.footerClass]}>
                                <FlexRender
                                    content={footer.column.columnDef.footer}
                                    context={footer.getContext()} />
                            </TableCell>
                        {/each}
                    </TableRow>
                {/each}
            </TableFooter>
        {/if}
    </TableRoot>

    {#if withTotal || withPagination}
        {@render Pagination({ table })}
    {/if}
</div>

{#snippet Filter({ table }: SnippetProps)}
    <div class="flex items-end gap-5 px-5">
        {#if withGlobalFilter}
            <Input
                class="h-8 w-40 lg:w-60"
                oninput={(e) => {
                    table.setGlobalFilter(String(e.currentTarget.value));
                }}
                placeholder="Cari..." />
        {/if}
        {@render filter?.({ table })}
    </div>
{/snippet}

{#snippet Pagination({ table }: SnippetProps)}
    {@const filteredQty = is_api
        ? (table_meta?.filter_quantity ?? table.getCoreRowModel()?.rows?.length)
        : table.getFilteredRowModel()?.rows?.length}
    {@const totalQty = is_api ? table_meta.total : table.getCoreRowModel()?.rows?.length}
    {@const hasFilters = totalQty != filteredQty}
    <div class="flex flex-col-reverse items-center justify-between gap-y-3 px-5 md:flex-row">
        {#if withTotal}
            <div class="text-muted-foreground flex-1 text-sm">
                Menampilkan
                {hasFilters ? `${filteredQty} dari total` : ''}
                {totalQty} data
            </div>
        {/if}

        {#if withPagination}
            <div class="flex items-center gap-x-6 lg:gap-x-8">
                <div class="flex items-center gap-x-2">
                    <p class="text-sm font-medium">Data per halaman</p>
                    <SelectRoot
                        allowDeselect={false}
                        onValueChange={(value) => {
                            table.setPageSize(Number(value));
                        }}
                        type="single"
                        value={`${table.getState().pagination.pageSize}`}>
                        <SelectTrigger class="h-8 w-[70px]">
                            {String(table.getState().pagination.pageSize)}
                        </SelectTrigger>
                        <SelectContent side="top">
                            {#each pageSizes as pageSize (pageSize)}
                                <SelectItem value={`${pageSize}`}>
                                    {pageSize}
                                </SelectItem>
                            {/each}
                        </SelectContent>
                    </SelectRoot>
                </div>
                <div class="flex items-center justify-center text-sm font-medium">
                    Halaman {table.getState().pagination.pageIndex + 1} dari
                    {table.getPageCount()}
                </div>
                <div class="flex items-center gap-x-2">
                    <Button
                        class="hidden size-8 p-0 lg:flex"
                        disabled={!table.getCanPreviousPage()}
                        onclick={() => table.setPageIndex(0)}
                        variant="outline">
                        <span class="sr-only">Go to first page</span>
                        <i class="iconify ph--caret-double-left-duotone size-4"></i>
                    </Button>
                    <Button
                        class="size-8 p-0"
                        disabled={!table.getCanPreviousPage()}
                        onclick={() => table.previousPage()}
                        variant="outline">
                        <span class="sr-only">Go to previous page</span>
                        <i class="iconify ph--caret-left-duotone size-4"></i>
                    </Button>
                    <Button
                        class="size-8 p-0"
                        disabled={!table.getCanNextPage()}
                        onclick={() => table.nextPage()}
                        variant="outline">
                        <span class="sr-only">Go to next page</span>
                        <i class="iconify ph--caret-right-duotone size-4"></i>
                    </Button>
                    <Button
                        class="hidden size-8 p-0 lg:flex"
                        disabled={!table.getCanNextPage()}
                        onclick={() => table.setPageIndex(table.getPageCount() - 1)}
                        variant="outline">
                        <span class="sr-only">Go to last page</span>
                        <i class="iconify ph--caret-double-right-duotone size-4"></i>
                    </Button>
                </div>
            </div>
        {/if}
    </div>
{/snippet}
