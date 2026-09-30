<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';

    import StringHelper from '@utilities/string-helper';

    import DecorationBadge from '@components/ui/decoration-badge.svelte';

    interface Props {
        category: App.Models.Category;
    }

    let { category }: Props = $props();

    const hex = $derived(getDecorationColor(category?.decorations?.color)?.hex);
    const icon = $derived(getDecorationIcon(category?.decorations?.icon)?.value);

    /* Lettermark fallback when the category has no decoration icon — the same
       visual contract as the category-select chips. */
    const visual = $derived({
        icon,
        text: icon ? undefined : StringHelper.getInitials(category?.name),
        background: hex ? `${hex}20` : undefined,
        color: hex ?? undefined,
    });
</script>

<div class="flex items-center gap-3 rounded-lg border border-border p-2">
    <DecorationBadge size="md" {...visual} />
    <div class="grow">
        {#if category.parent}
            <h6 class="text-sm font-medium tracking-wide text-base-content/80">
                {category.parent.name}
            </h6>
        {/if}
        <p class="text-sm font-bold">
            {category.name}
        </p>
    </div>
</div>
