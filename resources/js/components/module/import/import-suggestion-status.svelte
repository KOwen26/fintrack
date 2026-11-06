<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ImportController from '@wayfinder/App/Http/Controllers/ImportController';

    import Badge from '@components/ui/badge.svelte';
    import Button from '@components/ui/button.svelte';

    let { batch }: { batch: App.Models.ImportBatch } = $props();

    let retrying = $state(false);

    function retry(): void {
        retrying = true;
        router.post(
            ImportController.retrySuggestions.url({ batch: batch.id }),
            {},
            { onFinish: () => (retrying = false) }
        );
    }
</script>

<div class="flex flex-wrap items-center gap-2">
    {#if batch.suggestion_status === 'pending' || batch.suggestion_status === 'processing'}
        <Badge color="info" variant="soft">
            <i class="iconify size-3 animate-spin solar--refresh-line-duotone"></i>
            Suggestions Processing
        </Badge>
    {:else if batch.suggestion_status === 'completed'}
        <Badge color="success" variant="soft">Suggestions Applied — review before approving</Badge>
    {:else if batch.suggestion_status === 'failed'}
        <Badge color="warning" variant="soft">Suggestions Unavailable</Badge>

        {#if batch.status === 'staging'}
            <Button color="light" disabled={retrying} onclick={retry} size="sm" variant="outline">
                <i class={['iconify solar--refresh-line-duotone', retrying && 'animate-spin']}></i>

                {retrying ? 'Retrying…' : 'Retry'}
            </Button>
        {/if}
    {/if}
</div>
