<script lang="ts">
    import type { ImportRowData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import DateTimeHelper from '@utilities/date-time-helper';
    import Formatter from '@utilities/formatter';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import TableBody from '@components/ui/atoms/table/table-body.svelte';
    import TableCell from '@components/ui/atoms/table/table-cell.svelte';
    import TableHead from '@components/ui/atoms/table/table-head.svelte';
    import TableHeader from '@components/ui/atoms/table/table-header.svelte';
    import TableRow from '@components/ui/atoms/table/table-row.svelte';
    import Table from '@components/ui/atoms/table/table.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Checkbox from '@components/ui/forms/checkbox.svelte';

    let {
        rows,
        batch,
        onEdit,
        editable = true,
    }: {
        rows: ImportRowData[];
        batch: App.Models.ImportBatch;
        onEdit: (row: ImportRowData) => void;
        editable?: boolean;
    } = $props();

    function toggleInclude(row: ImportRowData): void {
        router.patch(
            ImportController.updateRow.url({ batch: batch.id, row: row.id }),
            { is_included: !row.is_included },
            { preserveScroll: true, only: ['batch', 'rows'] }
        );
    }

    function typeBadge(row: ImportRowData): 'success' | 'error' | 'info' | 'warning' {
        if (row.transaction_type === 'transfer') {
            return row.transfer_destination_account_id ? 'info' : 'warning';
        }

        return row.transaction_type === 'income' ? 'success' : 'error';
    }
</script>

<Table>
    <TableHeader>
        <TableRow>
            {#if editable}
                <TableHead class="w-10"></TableHead>
            {/if}
            <TableHead>Date</TableHead>
            <TableHead>Description</TableHead>
            <TableHead class="text-right">Type & Amount</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Flags</TableHead>
        </TableRow>
    </TableHeader>

    <TableBody>
        {#each rows as row (row.id)}
            <TableRow class={row.is_included ? '' : 'opacity-50'}>
                {#if editable}
                    <TableCell>
                        <div class="flex items-center">
                            <Checkbox
                                aria-label="Include row"
                                checked={row.is_included}
                                onCheckedChange={() => toggleInclude(row)} />

                            <Button
                                color="light"
                                onclick={() => onEdit(row)}
                                size="icon"
                                variant="ghost">
                                <i class="iconify size-4 solar--pen-new-square-line-duotone"></i>
                            </Button>

                            <div class="ml-2">
                                {row.row_number}.
                            </div>
                        </div>
                    </TableCell>
                {/if}
                <TableCell>
                    {DateTimeHelper.format(row.transaction_date, 'datetime')}
                </TableCell>
                <TableCell class="max-w-48 truncate" title={row.description ?? ''}>
                    {row.description ?? '—'}
                </TableCell>
                <TableCell class="font-medium">
                    <div class="flex items-center justify-between gap-3">
                        {#if row.transaction_type}
                            <Badge class="capitalize" color={typeBadge(row)} variant="soft">
                                {row.transaction_type}
                            </Badge>
                        {:else}
                            <span class="text-muted-foreground">—</span>
                        {/if}

                        <p class="text-right">
                            {Formatter.currency(row.amount ?? 0)}
                        </p>
                    </div>
                </TableCell>
                <TableCell>
                    <div class="space-y-3">
                        {#if row.category}
                            <div class="flex items-center gap-2">
                                <DecorationBadge decorations={row.category.decorations} size="sm" />
                                {row.category.name}
                            </div>
                        {:else}
                            <span class="text-muted-foreground">—</span>
                        {/if}
                    </div>
                </TableCell>
                <TableCell>
                    <div class="flex flex-wrap gap-1">
                        {#if row.is_duplicate}
                            <Badge color="warning" variant="outline-dash">Duplicate</Badge>
                        {/if}
                        {#if row.parse_errors}
                            <Badge color="error" variant="soft">
                                {Object.values(row.parse_errors)[0] ?? 'Error'}
                            </Badge>
                        {/if}
                    </div>
                </TableCell>
            </TableRow>
        {/each}
    </TableBody>
</Table>
