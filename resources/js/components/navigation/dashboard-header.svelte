<script lang="ts">
    import { flatMenu } from '@data/menu';
    import { page } from '@inertiajs/svelte';

    import { getTitleFromMenu } from '@utilities/helper.svelte';

    import Button from '@components/ui/button.svelte';
    import { sidebar } from '@states/reactive.svelte';

    const title = $derived(page.props?.meta?.title || getTitleFromMenu(flatMenu));
</script>

<header
    class={[
        'sticky top-0 z-50',
        'bg-base-100 text-base-content flex h-16 w-full items-center justify-between gap-3 px-6 py-3',
    ]}>
    <div class="flex items-center gap-3">
        {@render sidebarToggle()}
        {title}
    </div>
    <div>
        {@render profileInfo()}
    </div>
</header>

{#snippet sidebarToggle()}
    <button
        class="hidden cursor-pointer p-0.5 md:inline"
        aria-label="Sidebar Toggle"
        onclick={() => sidebar.collapse()}
        type="button">
        <div class="size-6">
            <i
                class={[
                    'size-6',
                    sidebar.is_collapsed
                        ? 'iconify ph--arrow-line-right-bold'
                        : 'iconify ph--arrow-line-left-bold',
                ]}></i>
        </div>
    </button>
{/snippet}

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
        class="dropdown dropdown-end bg-base-100 mt-2 w-52 rounded-lg shadow-sm"
        popover>
        <ul class="menu text-base-content w-full space-y-1">
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
