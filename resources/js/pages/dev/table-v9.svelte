<script lang="ts">
    import type { ColumnDef } from '@tanstack/svelte-table';
    import type { AppTableFeatures } from '@utilities/datatable-v9.svelte';

    import DevController from '@wayfinder/App/Http/Controllers/DevController';

    import { DataTable } from '@utilities/datatable-v9.svelte';
    import DateTimeHelper from '@utilities/date-time-helper';
    import { debounce } from '@utilities/helper.svelte';

    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import Datatable from '@components/ui/tables/datatable-v9.svelte';

    let { transactions } = $props();

    type TransactionRow = {
        id: number;
        transaction_date: string;
        description: string | null;
        amount: string;
        type: string;
    };

    const columns: ColumnDef<AppTableFeatures, TransactionRow>[] = [
        {
            accessorKey: 'id',
        },
        {
            accessorKey: 'transaction_date',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Date' }),
            cell: ({ row }) => DateTimeHelper.format(row.original.transaction_date, 'date'),
        },
        {
            accessorKey: 'description',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Description' }),
        },
        {
            accessorKey: 'amount',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Amount' }),
            cell: ({ row }) => Number(row.original.amount).toLocaleString('id-ID'),
        },
        {
            accessorKey: 'type',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Type' }),
        },
    ];

    const transactionTable = new DataTable<TransactionRow>(transactions, columns);

    const transactionTableServer = new DataTable<TransactionRow>(
        DevController.tableV9Server.url(),
        columns
    );
</script>

<Datatable dataTable={transactionTable} />
<hr />
<Datatable dataTable={transactionTableServer}>
    {#snippet filter({ table })}
        <Field title="Description">
            <Input
                oninput={debounce((e) =>
                    table.setColumnFilters([{ id: 'description', value: e.target.value }])
                )} />
        </Field>
    {/snippet}
</Datatable>
