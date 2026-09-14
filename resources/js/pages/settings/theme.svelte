<script lang="ts">
    import { THEMES, themeState, updateTheme } from '@hooks/use-theme.svelte';

    import Badge from '@components/ui/badge.svelte';
    import Card from '@components/ui/card.svelte';

    const state = themeState();
</script>

<div class="p-4">
    <h1 class="mb-4 text-xl font-bold">Theme</h1>

    <div class="grid grid-cols-2 gap-3">
        {#each THEMES as themeName (themeName)}
            <button class="text-left transition-all" onclick={() => updateTheme(themeName)}>
                <Card
                    class="border-2 {state.theme.value === themeName
                        ? 'border-primary'
                        : 'border-base-300'}">
                    <div class="mb-2 flex gap-1">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                        <span class="h-3 w-3 rounded-full bg-secondary"></span>
                        <span class="h-3 w-3 rounded-full bg-accent"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium capitalize">{themeName}</p>
                        {#if state.theme.value === themeName}
                            <Badge color="primary" variant="soft">Active</Badge>
                        {/if}
                    </div>
                </Card>
            </button>
        {/each}
    </div>
</div>
