<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';
    import settings from '@wayfinder/routes/settings';

    import DateTimeHelper from '@utilities/date-time-helper';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import Badge from '@components/ui/badge.svelte';
    import Link from '@components/ui/link.svelte';

    let { batches }: { batches: App.Models.ImportBatch[] } = $props();

    setLayoutProps({ title: 'Imports', backUrl: settings.index.url() });

    const statusColor: Record<string, 'info' | 'success' | 'warning'> = {
        staging: 'info',
        approved: 'success',
        discarded: 'warning',
    };
</script>

<MobilePageLayout variant="full">
    <h2 class="text-lg font-semibold">Imports</h2>

    <div class="mt-6 space-y-3">
        {#each batches as batch (batch.id)}
            <Link
                class="block rounded-xl border border-border p-4 transition-colors hover:bg-base-200"
                href={ImportController.show.url({ batch: batch.id })}>
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate text-sm font-semibold">
                        {batch.source_filename}
                    </span>
                    <Badge color={statusColor[batch.status] ?? 'info'} variant="soft">
                        {batch.status}
                    </Badge>
                </div>

                <p class="mt-1 text-sm text-muted-foreground">
                    {batch.account?.name ?? '—'} · {batch.provider?.name ?? '—'}
                </p>

                <p class="mt-1 text-xs text-muted-foreground">
                    {DateTimeHelper.format(batch.created_at, 'datetime')}
                    · {batch.imported_rows}/{batch.total_rows} imported
                </p>
            </Link>
        {:else}
            <EmptyItemPlaceholder label="No Imports Yet" />
        {/each}
    </div>
</MobilePageLayout>
