<script lang="ts">
    import type { ImportRowData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { router, usePoll } from '@inertiajs/svelte';
    import { pageLayoutProps } from '@lib/navigation-helper.svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ImportReviewTable from '@components/module/import/import-review-table.svelte';
    import ImportRowEditorModal from '@components/module/import/import-row-editor-form.svelte';
    import ImportStatusBadge from '@components/module/import/import-status-badge.svelte';
    import ImportSuggestionStatus from '@components/module/import/import-suggestion-status.svelte';
    import ProviderBadge from '@components/module/provider/provider-badge.svelte';
    import BottomActionBar from '@components/navigation/bottom-action-bar.svelte';
    import Button from '@components/ui/button.svelte';
    import ResponsiveCard from '@components/ui/cards/responsive-card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    let {
        batch,
        rows,
        accounts,
    }: {
        batch: App.Models.ImportBatch;
        rows: ImportRowData[];
        accounts: App.Models.Account[];
    } = $props();

    pageLayoutProps({
        title: 'Review Import',
        backUrl: AccountController.show.url({ account: batch.account_id }),
        hideBottomNav: true,
    });

    let editingRow = $state<ImportRowData | null>(null);
    let showDiscard = $state(false);
    let approving = $state(false);

    // Poll while suggestions run: partial reloads refresh batch/rows and let
    // show() lazily flip stale processing states. usePoll pauses itself when
    // the tab is inactive and cleans up on unmount.
    const poll = usePoll(3000, { only: ['batch', 'rows'] }, { autoStart: false });

    $effect(() => {
        const shouldPoll =
            batch.status === 'staging' &&
            (batch.suggestion_status === 'pending' || batch.suggestion_status === 'processing');

        if (shouldPoll) {
            poll.start();
        } else {
            poll.stop();
        }
    });

    const includedCount = $derived(rows.filter((row) => row.is_included).length);
    const duplicateCount = $derived(
        rows.filter((row) => row.is_duplicate && !row.is_included).length
    );
    const errorCount = $derived(rows.filter((row) => row.parse_errors).length);

    function approve(): void {
        approving = true;
        router.post(
            ImportController.approve.url({ batch: batch.id }),
            {},
            {
                onFinish: () => (approving = false),
            }
        );
    }

    function discard(): void {
        router.post(
            ImportController.discard.url({ batch: batch.id }),
            {},
            {
                onFinish: () => (showDiscard = false),
            }
        );
    }
</script>

<MobilePageLayout variant="full">
    <div class="mb-10 space-y-5">
        <div>
            <h2 class="text-lg font-semibold">Import For Account: {batch?.account?.name}</h2>
            <div class="flex items-center justify-between gap-3">
                <span class="grow text-sm text-muted-foreground">
                    {batch.source_filename}
                    <ProviderBadge class="ml-1.5" provider={batch?.provider} />
                </span>

                <ImportStatusBadge status={batch.status} />
            </div>

            {#if batch?.suggestion_status}
                <div class="mt-2">
                    <ImportSuggestionStatus {batch} />
                </div>
            {/if}
        </div>

        <ResponsiveCard onMobileStyle="card">
            <ImportReviewTable
                {batch}
                editable={batch.status === 'staging'}
                onEdit={(row) => (editingRow = row)}
                {rows} />
        </ResponsiveCard>
    </div>
</MobilePageLayout>

{#if batch.status === 'staging'}
    <BottomActionBar>
        <div class="space-y-3">
            <p class="text-sm">
                <span class="font-semibold">{includedCount}</span> included
                <span class="text-muted-foreground">
                    · {duplicateCount} duplicates excluded · {errorCount} errors
                </span>
            </p>

            <div class="flex items-center gap-2">
                <Button
                    class="min-w-1/4"
                    color="error"
                    onclick={() => (showDiscard = true)}
                    variant="outline">
                    Discard
                </Button>
                <Button
                    class="grow"
                    color="primary"
                    disabled={includedCount === 0 || approving}
                    onclick={approve}>
                    {approving ? 'Importing…' : `Import ${includedCount} rows`}
                </Button>
            </div>
        </div>
    </BottomActionBar>
{/if}

{#if editingRow}
    <ImportRowEditorModal {accounts} {batch} onClose={() => (editingRow = null)} row={editingRow} />
{/if}

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Discard"
    onConfirm={discard}
    title="Discard Import"
    bind:open={showDiscard}>
    The staged rows will be discarded. No transactions will be created.
</ConfirmationModal>
