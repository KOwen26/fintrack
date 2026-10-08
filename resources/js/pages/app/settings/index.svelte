<script lang="ts">
    import { router, setLayoutProps } from '@inertiajs/svelte';
    import { dashboard } from '@wayfinder/routes';
    import auth from '@wayfinder/routes/auth';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import SettingsMenu from '@components/module/settings/settings-menu.svelte';
    import Button from '@components/ui/button.svelte';

    setLayoutProps({ backUrl: dashboard.url() });
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
