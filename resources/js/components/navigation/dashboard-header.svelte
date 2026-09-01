<script lang="ts">
    import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';

    import * as Sidebar from '@components/ui/atoms/sidebar';
    import Breadcrumbs from '@components/ui/breadcrumbs.svelte';
    import Button from '@components/ui/button.svelte';

    interface Props {
        backUrl?: string;
        breadcrumbs?: BreadcrumbItem[];
    }

    let { backUrl = undefined, breadcrumbs = [] }: Props = $props();
</script>

<header
    class="flex h-14 shrink-0 items-center justify-between gap-2 border-b border-base-300 bg-white text-base-content transition-[width,height] ease-linear">
    <div class="flex items-center gap-2 px-4">
        <div class="hidden items-center gap-2 md:flex">
            <Sidebar.Trigger class="-ml-1" />
            <div class="divider mx-0 divider-horizontal divide-base-300"></div>
        </div>
        {#if backUrl}
            <Button class="size-8 p-1 btn-sm" color="secondary" href={backUrl} variant="ghost">
                <i class="iconify size-5 ph--arrow-left-bold"></i>
            </Button>
        {/if}
        <Breadcrumbs items={breadcrumbs} />
    </div>
    <div class="flex items-center gap-2 px-4">
        <Sidebar.Trigger class="md:hidden" />
        {@render profileInfo()}
    </div>
</header>

{#snippet profileInfo()}
    <Button
        style="anchor-name:--anchor-1"
        class="btn-circle btn-sm"
        color="accent"
        popovertarget="profile-info"
        variant="outline">
        <i class="iconify ph--user-bold"></i>
    </Button>
    <div
        id="profile-info"
        style="position-anchor:--anchor-1"
        class="dropdown dropdown-end mt-2 w-52 rounded-lg bg-base-100 shadow-sm"
        popover>
        <ul class="menu w-full space-y-1 text-base-content">
            <li class="menu-title">Title</li>
            <li><a><i class="iconify ph--user-bold"></i> Item 1</a></li>
            <li><a><i class="iconify ph--user-bold"></i> Item 2</a></li>
            <hr class="-mx-2" />
            <li><a><i class="iconify ph--user-bold"></i> Item 3</a></li>
            <li>
                <Button class="justify-start px-3" color="error" variant="soft">
                    <i class="iconify ph--sign-out-bold"></i>
                    Logout
                </Button>
            </li>
        </ul>
    </div>
{/snippet}
