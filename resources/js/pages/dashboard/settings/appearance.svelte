<script lang="ts" module>
    export { default as Layout } from '@components/layouts/dashboard-layout.svelte';
</script>

<script lang="ts">
    import Button from '@components/ui/button.svelte';

    type Theme = 'light' | 'dark' | 'system';

    const getSystemPreference = (): 'light' | 'dark' => {
        if (typeof window === 'undefined') return 'light';

        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    const getSavedTheme = (): Theme => {
        if (typeof window === 'undefined') return 'system';

        return (localStorage.getItem('theme') as Theme | null) ?? 'system';
    };

    const applyTheme = (theme: Theme) => {
        if (typeof document === 'undefined') return;
        const resolved = theme === 'system' ? getSystemPreference() : theme;
        document.documentElement.classList.toggle('dark', resolved === 'dark');
    };

    let currentTheme = $state<Theme>(getSavedTheme());

    $effect(() => {
        localStorage.setItem('theme', currentTheme);
        applyTheme(currentTheme);
    });
</script>

<div class="card bg-base-100 shadow-sm">
    <div class="card-body">
        <h2 class="card-title">Appearance</h2>
        <p class="text-base-content/70 text-sm">Choose your preferred color theme.</p>

        <div class="mt-4 flex gap-3">
            <Button
                onclick={() => (currentTheme = 'light')}
                variant={currentTheme === 'light' ? 'solid' : 'outline'}>
                Light
            </Button>
            <Button
                onclick={() => (currentTheme = 'dark')}
                variant={currentTheme === 'dark' ? 'solid' : 'outline'}>
                Dark
            </Button>
            <Button
                onclick={() => (currentTheme = 'system')}
                variant={currentTheme === 'system' ? 'solid' : 'outline'}>
                System
            </Button>
        </div>
    </div>
</div>
