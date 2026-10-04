<script lang="ts">
    import { setLayoutProps } from '@inertiajs/svelte';
    import { dashboard } from '@wayfinder/routes';

    import SettingsMenu from '@components/module/settings/settings-menu.svelte';

    setLayoutProps({ title: 'Settings', backUrl: dashboard.url() });
</script>

<SettingsMenu />
