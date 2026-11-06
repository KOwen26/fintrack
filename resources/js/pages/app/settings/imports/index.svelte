<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';
    import settings from '@wayfinder/routes/settings';

    import DateTimeHelper from '@utilities/date-time-helper';

    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ImportStatusBadge from '@components/module/import/import-status-badge.svelte';
    import ImportUploadForm from '@components/module/import/import-upload-form.svelte';
    import Button from '@components/ui/button.svelte';
    import Link from '@components/ui/link.svelte';

    let {
        accounts,
        batches,
    }: {
        accounts: App.Models.Account[];
        batches: App.Models.ImportBatch[];
    } = $props();

    let showUpload = $state(false);

    setLayoutProps({ title: 'Imports', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="full">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Imports</h2>

        <Button color="primary" onclick={() => (showUpload = true)} size="sm">
            <i class="iconify size-4 solar--upload-square-line-duotone"></i> Import
        </Button>
    </div>

    <div class="mt-5 space-y-3">
        {#each batches as batch (batch.id)}
            <Link
                class="block rounded-xl border border-border p-4 transition-colors hover:bg-card"
                href={ImportController.show.url({ batch: batch.id })}>
                <div class="flex items-center justify-between gap-3">
                    <span class="min-w-0 truncate font-semibold capitalize">
                        {batch?.account?.name}
                    </span>

                    <ImportStatusBadge status={batch.status} />
                </div>

                <p class="mt-1 text-sm text-muted-foreground">
                    {batch.source_filename} · {batch.provider?.name ?? '—'}
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

<ImportUploadForm {accounts} bind:open={showUpload} />
