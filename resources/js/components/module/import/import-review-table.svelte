<script lang="ts">
    import type { ImportRowData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import DateTimeHelper from '@utilities/date-time-helper';

    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import TableBody from '@components/ui/atoms/table/table-body.svelte';
    import TableCell from '@components/ui/atoms/table/table-cell.svelte';
    import TableHead from '@components/ui/atoms/table/table-head.svelte';
    import TableHeader from '@components/ui/atoms/table/table-header.svelte';
    import TableRoot from '@components/ui/atoms/table/table-root.svelte';
    import TableRow from '@components/ui/atoms/table/table-row.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Checkbox from '@components/ui/forms/checkbox.svelte';

    let {
        rows,
        batchId,
        onEdit,
        editable = true,
    }: {
        rows: ImportRowData[];
        batchId: number;
        onEdit: (row: ImportRowData) => void;
        editable?: boolean;
    } = $props();

    const currency = new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0,
    });

    function toggleInclude(row: App.Models.ImportRow): void {
        router.patch(
            ImportController.updateRow.url({ batch: batchId, row: row.id }),
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

    function typeLabel(row: ImportRowData): string {
        if (row.transaction_type === 'transfer' && !row.transfer_destination_account_id) {
            return 'transfer — set destination';
        }

        return row.transaction_type ?? '';
    }
</script>

<TableRoot>
    <TableHeader>
        <TableRow>
            {#if editable}
                <TableHead class="w-10"></TableHead>
            {/if}
            <TableHead>Date</TableHead>
            <TableHead>Description</TableHead>
            <TableHead class="text-right">Amount</TableHead>
            <TableHead>Type</TableHead>
            <TableHead>Category</TableHead>
            <TableHead>Flags</TableHead>
            {#if editable}
                <TableHead class="w-10"></TableHead>
            {/if}
        </TableRow>
    </TableHeader>
    <TableBody>
        {#each rows as row (row.id)}
            <TableRow class={row.is_included ? '' : 'opacity-50'}>
                {#if editable}
                    <TableCell>
                        <Checkbox
                            aria-label="Include row"
                            checked={row.is_included}
                            onCheckedChange={() => toggleInclude(row)} />
                    </TableCell>
                {/if}
                <TableCell>
                    {DateTimeHelper.format(row.transaction_date, 'datetime')}
                </TableCell>
                <TableCell class="max-w-48 truncate" title={row.description ?? ''}>
                    {row.description ?? '—'}
                </TableCell>
                <TableCell class="text-right font-medium">
                    {currency.format(row.amount ?? 0)}
                </TableCell>
                <TableCell>
                    {#if row.transaction_type}
                        <Badge color={typeBadge(row)} variant="soft">
                            {typeLabel(row)}
                        </Badge>
                    {:else}
                        <span class="text-muted-foreground">—</span>
                    {/if}
                </TableCell>
                <TableCell>
                    {#if row.category}
                        <div class="flex items-center gap-2">
                            <DecorationBadge decorations={row.category.decorations} size="sm" />
                            {row.category.name}
                        </div>
                    {:else}
                        <span class="text-muted-foreground">—</span>
                    {/if}
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
                {#if editable}
                    <TableCell>
                        <Button
                            class="btn-circle btn-sm"
                            color="light"
                            onclick={() => onEdit(row)}
                            variant="ghost">
                            <i class="iconify size-4 solar--pen-new-square-line-duotone"></i>
                        </Button>
                    </TableCell>
                {/if}
            </TableRow>
        {/each}
    </TableBody>
</TableRoot>
