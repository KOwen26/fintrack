<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { DataComposer } from '@lib/data-composer';

    import { templateSchema } from '@schema/template.schema';

    import DataList from '@components/data/data-list.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    const details = $derived(DataComposer.from(templateSchema).toDataDisplay(template));
</script>

<DataList data={details} />
