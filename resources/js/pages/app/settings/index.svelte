<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import { pageLayoutProps } from '@lib/navigation-helper.svelte';
    import { dashboard } from '@wayfinder/routes';
    import auth from '@wayfinder/routes/auth';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import SettingsMenu from '@components/module/settings/settings-menu.svelte';
    import Button from '@components/ui/button.svelte';

    pageLayoutProps({ backUrl: dashboard.url(), title: 'Settings' });
</script>

<MobilePageLayout variant="4/5">
    <div class="space-y-6">
        <SettingsMenu />

        <Button
            class="w-full"
            color="error"
            onclick={() => {
                router.post(auth.logout.url());
            }}
            variant="outline">
            <i class="iconify size-5 solar--logout-2-bold-duotone"></i>
            Logout
        </Button>
    </div>
</MobilePageLayout>
