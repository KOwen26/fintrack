<script lang="ts">
    import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';
    import type { HeaderContext } from '@type/header-context';

    import * as Sidebar from '@components/ui/atoms/sidebar';
    import Breadcrumbs from '@components/ui/breadcrumbs.svelte';
    import Button from '@components/ui/button.svelte';
    import Separator from '@components/ui/separator.svelte';

    interface Props {
        backUrl?: string;
        breadcrumbs?: BreadcrumbItem[];
        headerContext?: HeaderContext;
    }

    let { backUrl = undefined, breadcrumbs = [], headerContext = undefined }: Props = $props();
</script>

<header
    class="hidden h-14 shrink-0 items-center justify-between gap-2 border-b border-base-300 bg-white px-4 text-base-content transition-[width,height] ease-linear md:flex">
    <div class="flex min-w-0 items-center gap-2">
        <div class="flex items-center gap-2">
            <Sidebar.Trigger class="-ml-1" />
            <Separator orientation="vertical" />
        </div>

        {#if backUrl}
            <Button color="secondary" href={backUrl} size="icon" variant="ghost">
                <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
            </Button>
        {/if}

        {#if breadcrumbs?.length}
            <Breadcrumbs items={breadcrumbs} />
        {/if}
    </div>

    <div class="flex items-center gap-2">
        {#if headerContext}
            <div class="flex items-center gap-2">
                {@render headerContext.render()}
            </div>
        {/if}
        {@render profileInfo()}
    </div>
</header>

{#snippet profileInfo()}
    <Button
        style="anchor-name:--anchor-1"
        class="hidden rounded-full md:inline-flex"
        color="accent"
        size="icon-sm"
        variant="outline">
        <i class="iconify solar--user-bold-duotone"></i>
    </Button>
{/snippet}
