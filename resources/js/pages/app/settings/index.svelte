<script lang="ts">
    import { setLayoutProps } from '@inertiajs/svelte';
    import { dashboard } from '@wayfinder/routes';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import SettingsMenu from '@components/module/settings/settings-menu.svelte';

    setLayoutProps({ backUrl: dashboard.url() });
</script>

<MobilePageLayout variant="4/5">
    <div class="space-y-6">
        <SettingsMenu />
    </div>
</MobilePageLayout>
