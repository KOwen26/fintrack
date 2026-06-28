<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';
    import { getDecorationIcon } from '@data/decoration-icons';

    interface Props {
        category: App.Models.Category | null;
    }

    let { category }: Props = $props();

    const colorHex = $derived(
        category?.decorations?.color
            ? (getDecorationColor(category.decorations.color)?.value ?? undefined)
            : undefined
    );

    const iconClass = $derived(
        category?.decorations?.icon
            ? (getDecorationIcon(category.decorations.icon)?.value ?? 'ph--tag-bold')
            : 'ph--tag-bold'
    );
</script>

{#if category}
    <div class="flex items-center gap-3">
        <div
            style:background={colorHex ? `${colorHex}20` : undefined}
            style:color={colorHex ?? undefined}
            class="size-10 rounded-xl bg-base-content/10 flex items-center justify-center shrink-0 text-lg">
            <i class="iconify size-5 {iconClass}"></i>
        </div>
        <div class="flex-1 min-w-0">
            {#if category.parent}
                <p class="text-xs text-base-content/40 truncate">{category.parent.name}</p>
            {/if}
            <p class="font-semibold text-sm text-base-content truncate">
                {category.name}
            </p>
        </div>
        <i class="iconify size-5 text-base-content/20 ph--caret-right-bold"></i>
    </div>
{/if}
