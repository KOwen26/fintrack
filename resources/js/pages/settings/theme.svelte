<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { page, router } from '@inertiajs/svelte';
    import UserThemeController from '@wayfinder/App/Http/Controllers/UserThemeController';

    import Badge from '@components/ui/badge.svelte';
    import Card from '@components/ui/card.svelte';

    const daisyThemes = [
        'light',
        'dark',
        'cupcake',
        'bumblebee',
        'emerald',
        'corporate',
        'synthwave',
        'retro',
        'cyberpunk',
        'valentine',
        'halloween',
        'forest',
        'aqua',
        'lofi',
        'pastel',
        'fantasy',
        'black',
        'luxury',
        'dracula',
        'business',
        'night',
        'coffee',
        'winter',
        'dim',
        'nord',
        'sunset',
    ];

    const currentTheme = $derived(
        (page.props.auth?.user as App.Models.User | null)?.theme_preference ?? 'light'
    );

    function selectTheme(theme: string) {
        document.documentElement.dataset.theme = theme;
        router.put(
            UserThemeController.update.url(),
            { theme },
            {
                preserveScroll: true,
                preserveState: true,
            }
        );
    }
</script>

<div class="p-4">
    <h1 class="mb-4 text-xl font-bold">Theme</h1>

    <div class="grid grid-cols-2 gap-3">
        {#each daisyThemes as theme (theme)}
            <button class="text-left transition-all" onclick={() => selectTheme(theme)}>
                <Card
                    class="border-2 {currentTheme === theme
                        ? 'border-primary'
                        : 'border-base-300'}">
                    <div class="mb-2 flex gap-1">
                        <span class="h-3 w-3 rounded-full bg-primary"></span>
                        <span class="h-3 w-3 rounded-full bg-secondary"></span>
                        <span class="h-3 w-3 rounded-full bg-accent"></span>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-medium capitalize">{theme}</p>
                        {#if currentTheme === theme}
                            <Badge color="primary" variant="soft">Active</Badge>
                        {/if}
                    </div>
                </Card>
            </button>
        {/each}
    </div>
</div>
