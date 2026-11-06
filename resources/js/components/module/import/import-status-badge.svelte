<script lang="ts">
    import type { ColorVariant } from '@/data/theme';
    import type { App } from '@wayfinder/types';

    import ImportBatchStatus from '@wayfinder/App/Enums/ImportBatchStatus';

    import Badge from '@components/ui/badge.svelte';

    let { status, ...props }: { status: App.Enums.ImportBatchStatus } = $props();

    const config: Record<App.Enums.ImportBatchStatus, { label: string; color: ColorVariant }> = {
        [ImportBatchStatus.Staging]: { label: 'Staging', color: 'info' },
        [ImportBatchStatus.Approved]: { label: 'Approved', color: 'success' },
        [ImportBatchStatus.Discarded]: { label: 'Discarded', color: 'error' },
    };

    const badge = $derived(config[status]);
</script>

<Badge color={badge.color} variant="soft" {...props}>{badge.label}</Badge>
