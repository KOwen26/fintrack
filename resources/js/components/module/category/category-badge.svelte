<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { getDecorationColor } from '@data/decoration-colors';

    interface Props {
        category: App.Models.Category;
    }

    let { category }: Props = $props();

    const colorHex = $derived(
        category.decorations?.color
            ? getDecorationColor(category.decorations.color)?.value
            : undefined
    );
</script>

<span style:color={colorHex} class="font-medium">
    {category.name}
</span>
