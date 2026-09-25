<script lang="ts">
    import type { RestProps } from '@type/index';

    import ErrorWrapper from './error-wrapper.svelte';

    import { IsMobile } from '@/svelte/is-mobile.svelte.js';
    import { flatMenu, transformMenuToBreadcrumbs } from '@data/menu';
    import { useFlashToast } from '@hooks/flash-handler.svelte';
    import { page } from '@inertiajs/svelte';

    import { getBreadcrumbItems } from '@utilities/global-states.svelte';
    import { getTitleFromMenu } from '@utilities/helper.svelte';

    import BottomNav from '@components/navigation/bottom-nav.svelte';
    import DashboardHeaderMobile from '@components/navigation/dashboard-header-mobile.svelte';
    import DashboardHeader from '@components/navigation/dashboard-header.svelte';
    import DashboardSidebar from '@components/navigation/dashboard-sidebar.svelte';
    import * as Sidebar from '@components/ui/atoms/sidebar';
    import Toaster from '@components/ui/toaster.svelte';

    let {
        meta,
        backUrl = undefined,
        breadcrumbs = [],
        title: layoutTitle = undefined,
        headerContext = undefined,
        mobileShellClass = undefined,
        children,
        ...props
    }: RestProps = $props();

    // setLayoutProps({ title }) wins, then the shared meta title, then the active menu.
    const title = $derived(layoutTitle ?? (page.props?.meta?.title || getTitleFromMenu(flatMenu)));
    const appName = import.meta.env.VITE_APP_NAME || page?.props?.meta?.app_name;

    useFlashToast();

    // The sidebar shell is desktop-only; mobile renders a plain stacked layout.
    const isMobile = new IsMobile();

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

{#if isMobile.current}
    <div class="relative flex min-h-svh w-full flex-1 flex-col {mobileShellClass}">
        <DashboardHeaderMobile {backUrl} {headerContext} {title} />

        {@render pageContent()}

        <BottomNav />
    </div>
{:else}
    <Sidebar.Provider>
        <DashboardSidebar />
        <Sidebar.Inset>
            <DashboardHeader {backUrl} breadcrumbs={breadcrumbItems} {headerContext} />

            {@render pageContent()}

            {@render footer()}
        </Sidebar.Inset>
    </Sidebar.Provider>
{/if}

{#snippet pageContent()}
    <ErrorWrapper>
        <!-- Mobile padding is page-controlled; desktop keeps the shared padding. -->
        <div class="flex h-full flex-col gap-6 md:p-5">
            {@render children?.()}
        </div>
    </ErrorWrapper>
{/snippet}

{#snippet footer()}
    <footer></footer>
{/snippet}
