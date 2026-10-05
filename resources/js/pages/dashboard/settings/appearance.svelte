<script lang="ts">
    import { setLayoutProps } from '@inertiajs/svelte';
    import settings from '@wayfinder/routes/settings';

    import ThemeSelector from '@components/ui/theme-selector.svelte';

    setLayoutProps({ title: 'Settings', backUrl: settings.index.url() });
</script>

<ThemeSelector />
