<script lang="ts">
    import type { App } from '@wayfinder/types';

    interface Props {
        category: App.Models.Category;
    }

    let { category }: Props = $props();
</script>

<span style:color={category.decorations?.color?.value} class="font-medium">
    {category.name}
</span>
