<script lang="ts">
    import type { ColumnDef } from '@tanstack/table-core';

    import { DataTable } from '@utilities/datatable.svelte';
    import { debounce } from '@utilities/helper.svelte';

    import Field from '@components/ui/forms/field.svelte';
    import Input from '@components/ui/forms/input.svelte';
    import Datatable from '@components/ui/tables/datatable.svelte';

    let { users } = $props();

    type User = {
        id: number;
        name: string;
        email: string;
    };

    const columns: ColumnDef<User>[] = [
        {
            accessorKey: 'id',
        },
        {
            accessorKey: 'name',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Name' }),
        },
        {
            accessorKey: 'email',
            header: ({ column }) => DataTable.sortableHeader({ column, title: 'Email' }),
        },
    ];

    const userTable = new DataTable<User>(users, columns);

    const userTableServer = new DataTable<User>('/dev/table/server', columns);
</script>

<Datatable dataTable={userTable} />
<hr />
<Datatable dataTable={userTableServer}>
    {#snippet filter({ table })}
        <Field title="Email">
            <Input
                oninput={debounce((e) =>
                    table.setColumnFilters([{ id: 'email', value: e.target.value }])
                )} />
        </Field>
    {/snippet}
</Datatable>
