<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';

    interface Props {
        category: App.Models.Category;
    }

    let { category }: Props = $props();

    const color = $derived(
        category?.decorations?.color
            ? (getDecorationColor(category.decorations.color)?.hex ?? undefined)
            : undefined
    );

    const iconClass = $derived(
        category?.decorations?.icon
            ? (getDecorationIcon(category.decorations.icon)?.value ?? 'solar--tag-bold-duotone')
            : 'solar--tag-bold-duotone'
    );
</script>

<div class="flex items-center gap-3">
    <div
        style:background={color ? `${color}20` : undefined}
        style:color={color ?? undefined}
        class="flex size-10 shrink items-center justify-center rounded-xl">
        <i class="iconify size-5 {iconClass}"></i>
    </div>
    <div class="grow">
        {#if category.parent}
            <p class="truncate text-sm text-base-content/50">{category.parent.name}</p>
        {/if}
        <p class="truncate text-sm font-semibold">
            {category.name}
        </p>
    </div>
    <i class="iconify size-5 text-base-content/60 solar--alt-arrow-right-line-duotone"></i>
</div>
