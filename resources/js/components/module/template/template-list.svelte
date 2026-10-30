<script lang="ts">
    import type { CategoryData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';

    import { templateSubtitle } from '@schema/template.schema';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import EmptyItemPlaceholder from '@components/data/empty-item-placeholder.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Card from '@components/ui/cards/card.svelte';
    import Link from '@components/ui/link.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: CategoryData[];
    } = $props();
</script>

{#if templates.length === 0}
    <EmptyItemPlaceholder
        ctaLabel="Create your first template"
        ctaUrl={TransactionTemplateController.create.url()}
        icon="solar--notes-line-duotone"
        label="No templates yet" />
{:else}
    <ul class="space-y-1">
        {#each templates as template (template.id)}
            <li>
                <Link href={TransactionTemplateController.show.url({ template: template.id })}>
                    <Card>
                        <div class="flex items-center gap-3">
                            <DecorationBadge decorations={template.decorations} size="sm" />

                            <span class="min-w-0 grow">
                                <h6 class="truncate text-sm font-semibold">{template.name}</h6>

                                <p class="truncate text-xs text-muted-foreground">
                                    {templateSubtitle(template, categories)}
                                </p>
                            </span>

                            <CurrencyAmount
                                class="shrink-0 text-sm font-medium"
                                value={Number(template.amount)} />

                            <i
                                class="iconify size-5 shrink-0 text-muted-foreground solar--alt-arrow-right-linear"
                                aria-hidden="true"></i>
                        </div>
                    </Card>
                </Link>
            </li>
        {/each}
    </ul>
{/if}
