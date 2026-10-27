<script lang="ts">
    import { decorationIcons } from '@data/decorations';

    import DrawerModal from '@components/ui/drawer-modal.svelte';
    import Popover from '@components/ui/popover.svelte';
    import ScrollArea from '@components/ui/scroll-area.svelte';

    interface Props {
        value?: string;
        onchange?: (slug: string) => void;
        rows?: number;
        variant?: 'drawer-modal' | 'popover';
    }

    let { value = $bindable(''), onchange, rows = 2, variant = 'drawer-modal' }: Props = $props();

    let open = $state(false);
    let gridEl: HTMLDivElement | undefined;
    let columnCount = $state(8);

    $effect(() => {
        if (!gridEl) {
            return;
        }
        const measure = (): void => {
            const tracks = getComputedStyle(gridEl!).gridTemplateColumns;
            const count = tracks.split(' ').filter(Boolean).length;
            columnCount = count > 0 ? count : 8;
        };
        measure();
        const observer = new ResizeObserver(measure);
        observer.observe(gridEl);

        return () => observer.disconnect();
    });

    const selectedItem = $derived(decorationIcons.find((icon) => icon.slug === value));
    const hasSelection = $derived(!!selectedItem);
    const palette = $derived(decorationIcons.filter((icon) => icon.slug !== value));

    const total = $derived(rows * columnCount);
    const seeMoreNeeded = $derived(palette.length > total - 2);
    const listSlots = $derived(seeMoreNeeded ? total - 2 : total - 1);
    const visible = $derived(palette.slice(0, listSlots));
    const remaining = $derived(palette.length - listSlots);

    function select(slug: string): void {
        value = slug;
        onchange?.(slug);
        open = false;
    }
</script>

<div class="@container">
    <div
        bind:this={gridEl}
        class="grid grid-cols-4 gap-2 @sm:grid-cols-8 @md:grid-cols-10 @xl:grid-cols-12 @3xl:grid-cols-24">
        {#if hasSelection && selectedItem}
            {@render item(selectedItem)}
        {:else}
            <div
                class="size-10 rounded-md border border-dashed border-foreground/20"
                aria-hidden="true">
            </div>
        {/if}

        {#each visible as icon (icon.slug)}
            {@render item(icon)}
        {/each}

        {#if seeMoreNeeded}
            {#if variant === 'popover'}
                <Popover
                    class="w-100 p-2"
                    align="end"
                    sideOffset={8}
                    triggerProps={{ 'aria-label': 'See more' }}
                    bind:open>
                    {#snippet trigger()}
                        +{remaining}
                    {/snippet}

                    {@render itemGrid()}
                </Popover>
            {:else}
                <button
                    class="flex size-10 items-center justify-center rounded-md border border-dashed border-input/50 text-xs font-semibold text-foreground/70 transition hover:bg-card"
                    aria-label="See more"
                    onclick={() => (open = true)}
                    type="button">
                    +{remaining}
                </button>
            {/if}
        {/if}
    </div>
</div>

{#if variant === 'drawer-modal'}
    <DrawerModal title="Select Icon" bind:open>
        <div class="px-4 pb-5">
            {@render itemGrid()}
        </div>
    </DrawerModal>
{/if}

{#snippet item(icon)}
    <button
        class={[
            'flex size-10 items-center justify-center rounded-md border border-input/50 text-foreground transition hover:scale-105 ',
            value === icon.slug ? 'border-transparent bg-primary/20 ring-[1.5px] ring-primary' : '',
        ]}
        aria-label={icon.label}
        aria-pressed={value === icon.slug}
        onclick={() => select(icon.slug)}
        type="button">
        <i class="iconify {icon.value} size-6"></i>
    </button>
{/snippet}

{#snippet itemGrid()}
    <ScrollArea rootClass="max-h-[60vh] w-full @container">
        <div class="grid grid-cols-8 gap-2 p-1 @md:grid-cols-10 @xl:grid-cols-12">
            {#each decorationIcons as icon (icon.slug)}
                {@render item(icon)}
            {/each}
        </div>
    </ScrollArea>
{/snippet}
