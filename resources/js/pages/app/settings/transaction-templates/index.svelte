<script lang="ts">
    import type { CategoryData } from '@type/generated';
    import type { App } from '@wayfinder/types';

    import { setLayoutProps } from '@inertiajs/svelte';
    import TransactionTemplateController from '@wayfinder/App/Http/Controllers/Settings/TransactionTemplateController';
    import settings from '@wayfinder/routes/settings';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';
    import TemplateList from '@components/module/template/template-list.svelte';
    import Button from '@components/ui/button.svelte';

    let {
        templates,
        categories,
    }: {
        templates: App.Models.TransactionTemplate[];
        categories: CategoryData[];
    } = $props();

    setLayoutProps({ title: 'Transaction Templates', backUrl: settings.index.url() });
</script>

<MobilePageLayout variant="full">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Templates</h2>

        <Button color="primary" href={TransactionTemplateController.create.url()}>
            Add
            <i class="iconify size-5 solar--add-bold-duotone"></i>
        </Button>
    </div>

    <div class="mt-6">
        <TemplateList {categories} {templates} />
    </div>
</MobilePageLayout>
