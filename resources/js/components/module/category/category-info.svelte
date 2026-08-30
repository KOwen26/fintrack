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
            ? (getDecorationIcon(category.decorations.icon)?.value ?? 'ph--tag-bold')
            : 'ph--tag-bold'
    );
</script>

<div class="flex items-center gap-3">
    <div
        style:background={color ? `${color}20` : undefined}
        style:color={color ?? undefined}
        class="size-10 rounded-xl flex items-center justify-center shrink">
        <i class="iconify size-5 {iconClass}"></i>
    </div>
    <div class="grow">
        {#if category.parent}
            <p class="text-sm text-base-content/50 truncate">{category.parent.name}</p>
        {/if}
        <p class="font-semibold text-sm truncate">
            {category.name}
        </p>
    </div>
    <i class="iconify size-5 text-base-content/60 ph--caret-right-bold"></i>
</div>
