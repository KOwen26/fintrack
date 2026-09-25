<script lang="ts">
    import type { HeaderContext } from '@type/header-context';

    import Button from '@components/ui/button.svelte';

    interface Props {
        backUrl?: string;
        headerContext?: HeaderContext;
        title?: string;
    }

    let { backUrl = undefined, headerContext = undefined, title = undefined }: Props = $props();
</script>

<!--
    Mobile context bar. A separate design from the desktop header: flat,
    safe-area aware, mobile page padding, and fully owned by the page context
    when one is declared.
-->
<header
    class="flex min-h-14 items-center gap-2 px-3 pt-[env(safe-area-inset-top)] text-base-content md:hidden">
    {#if headerContext}
        <div class="flex min-w-0 flex-1 items-center gap-2">
            {@render headerContext.render()}
        </div>
    {:else}
        {#if backUrl}
            <Button
                class="size-10 shrink-0 p-1 btn-sm"
                color="secondary"
                href={backUrl}
                variant="ghost">
                <i class="iconify size-6 solar--arrow-left-line-duotone"></i>
            </Button>
        {/if}
        {#if title}
            <span class="truncate text-xl font-bold">{title}</span>
        {/if}
    {/if}
</header>
