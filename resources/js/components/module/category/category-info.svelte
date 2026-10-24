<script lang="ts">
    import type { CategoryData } from '@type/generated';

    import DecorationBadge from '@components/module/decoration-badge.svelte';

    interface Props {
        category: CategoryData;
    }

    let { category }: Props = $props();
</script>

<div class="flex items-center gap-3 rounded-lg border border-border p-2">
    <DecorationBadge decorations={category.decorations} size="md" />
    <div class="grow">
        {#if category.group}
            <h6 class="text-sm font-medium tracking-wide text-base-content/80">
                {category.group.name}
            </h6>
        {/if}
        <p class="text-sm font-bold">
            {category.name}
        </p>
    </div>
</div>
