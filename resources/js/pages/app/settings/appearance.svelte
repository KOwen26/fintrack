<script lang="ts">
    import { setLayoutProps } from '@inertiajs/svelte';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import ThemeSelector from '@components/ui/theme-selector.svelte';

    setLayoutProps({ title: 'Appearance', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="full">
    <ThemeSelector />
</MobilePageLayout>
