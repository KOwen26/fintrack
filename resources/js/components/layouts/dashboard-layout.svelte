<script lang="ts">
    import type { RestProps } from '@type/index';

    import ErrorWrapper from './error-wrapper.svelte';

    import { flatMenu, transformMenuToBreadcrumbs } from '@data/menu';
    import { useFlashToast } from '@hooks/flash-handler.svelte';
    import { page } from '@inertiajs/svelte';

    import { getBreadcrumbItems } from '@utilities/global-states.svelte';
    import { getTitleFromMenu } from '@utilities/helper.svelte';

    import BottomNav from '@components/navigation/bottom-nav.svelte';
    import DashboardSidebar from '@components/navigation/dashboard-sidebar.svelte';
    import * as Sidebar from '@components/ui/atoms/sidebar';
    import Breadcrumbs from '@components/ui/breadcrumbs.svelte';
    import Button from '@components/ui/button.svelte';
    import Toaster from '@components/ui/toaster.svelte';

    let { meta, backUrl = undefined, breadcrumbs = [], children, ...props }: RestProps = $props();

    const title = $derived(page.props?.meta?.title || getTitleFromMenu(flatMenu));
    const appName = import.meta.env.VITE_APP_NAME || page?.props?.meta?.app_name;

    useFlashToast();

    const menuBreadcrumbs = $derived(transformMenuToBreadcrumbs());
    const currentMenuBreadcrumbs = $derived(
        menuBreadcrumbs?.find((val) =>
            val?.breadcrumbs?.find((v) => v?.route === meta?.current_route_name)
        )?.breadcrumbs
    );

    const breadcrumbItems = $derived.by(() => {
        const pageBreadcrumb = breadcrumbs ?? getBreadcrumbItems();
        const menuBreadcrumbs = currentMenuBreadcrumbs;

        const final = pageBreadcrumb?.length ? pageBreadcrumb : menuBreadcrumbs;

        return final?.length ? final : [];
    });
</script>

<svelte:head>
    <title>{appName} {title?.length ? `| ${title}` : ''}</title>
</svelte:head>

<Toaster />

<Sidebar.Provider>
    <DashboardSidebar />
    <Sidebar.Inset>
        {@render header()}

        <ErrorWrapper>
            <div class="flex h-full flex-col gap-6 p-3 md:p-5">
                {@render children?.()}
            </div>

            <div class="my-10"></div>

            {@render footer()}
        </ErrorWrapper>

        <BottomNav />
    </Sidebar.Inset>
</Sidebar.Provider>

{#snippet header()}
    <header
        class="flex h-14 shrink-0 items-center gap-2 border-b border-base-300 bg-white text-base-content transition-[width,height] ease-linear">
        <div class="flex items-center gap-2 px-4">
            <Sidebar.Trigger class="-ml-1" />
            <div class="divider mx-0 divider-horizontal divide-base-300"></div>
            {#if backUrl}
                <Button class="size-8 p-1 btn-sm" color="secondary" href={backUrl} variant="ghost">
                    <i class="iconify size-5 ph--arrow-left-bold"></i>
                </Button>
            {/if}
            <Breadcrumbs items={breadcrumbItems} />
        </div>
    </header>
{/snippet}

{#snippet footer()}
    <footer></footer>
{/snippet}
