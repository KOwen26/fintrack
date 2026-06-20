<script lang="ts">
    import type { App } from '@wayfinder/types';

    let { account }: { account: App.Models.Account } = $props();
</script>

<span
    class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-xs font-medium"
    style="background-color: {account.decorations?.color?.value}20; color: {account.decorations
        ?.color?.value}">
    {account.name}
</span>
