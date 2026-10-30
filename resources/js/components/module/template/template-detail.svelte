<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { DataComposer } from '@lib/data-composer';

    import { templateSchema } from '@schema/template.schema';

    import StringHelper from '@utilities/string-helper';

    import DataList from '@components/data/data-list.svelte';
    import Badge from '@components/ui/badge.svelte';

    let { template }: { template: App.Models.TransactionTemplate } = $props();

    const details = $derived(DataComposer.from(templateSchema).toDataDisplay(template));
</script>

<DataList data={details}>
    {#snippet append()}
        {#if template.tags?.length}
            <div class="border-t pt-2">
                <div class="flex flex-wrap gap-1.5">
                    {#each template.tags as tag (tag)}
                        <Badge color="secondary" shape="pill" size="sm">
                            {StringHelper.snakeToTitle(tag)}
                        </Badge>
                    {/each}
                </div>
            </div>
        {/if}
    {/snippet}
</DataList>
