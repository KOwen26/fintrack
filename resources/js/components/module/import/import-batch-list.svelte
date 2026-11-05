<script lang="ts">
    import type { App } from '@wayfinder/types';

    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import DateTimeHelper from '@utilities/date-time-helper';

    import Badge from '@components/ui/badge.svelte';
    import Link from '@components/ui/link.svelte';

    let { imports }: { imports: App.Models.ImportBatch[] } = $props();

    const statusConfig: Record<string, { label: string; color: 'info' | 'success' | 'warning' }> = {
        staging: { label: 'Needs Review', color: 'info' },
        approved: { label: 'Approved', color: 'success' },
        discarded: { label: 'Discarded', color: 'warning' },
    };

    function rowCount(batch: App.Models.ImportBatch): string {
        if (batch.status === 'staging') {
            return `${batch.total_rows} rows staged`;
        }

        return `${batch.imported_rows}/${batch.total_rows} imported`;
    }
</script>

{#if imports?.length === 0}
    <p class="text-sm text-muted-foreground">No imports yet.</p>
{:else}
    <ul class="-mx-2 divide-y divide-border">
        {#each imports as batch (batch.id)}
            {@const config = statusConfig[batch.status] ?? { label: batch.status, color: 'info' }}
            <li>
                <Link
                    class="flex items-center justify-between gap-3 rounded-lg px-2 py-2.5 transition-colors hover:bg-base-200"
                    href={ImportController.show.url({ batch: batch.id })}>
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-foreground">
                            {batch.source_filename}
                        </span>
                        <span class="mt-0.5 block text-xs text-muted-foreground">
                            {DateTimeHelper.format(batch.created_at, 'datetime')}
                            · {rowCount(batch)}
                        </span>
                    </span>

                    <Badge color={config.color} shape="pill" variant="soft">
                        {config.label}
                    </Badge>
                </Link>
            </li>
        {/each}
    </ul>
{/if}
