<script lang="ts">
    import { getActiveDecorationColors } from '@data/decoration-colors';

    import Drawer from '@components/ui/drawer.svelte';
    import Modal from '@components/ui/modal.svelte';
    import Popover from '@components/ui/popover.svelte';
    import ScrollArea from '@components/ui/scroll-area.svelte';

    interface Props {
        value?: string;
        onchange?: (slug: string) => void;
        rows?: number;
        variant?: 'modal' | 'popover' | 'drawer';
    }

    let { value = $bindable(''), onchange, rows = 2, variant = 'modal' }: Props = $props();

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

    const decorationColors = getActiveDecorationColors();
    const selectedItem = $derived(decorationColors.find((color) => color.slug === value));
    const hasSelection = $derived(!!selectedItem);
    const palette = $derived(decorationColors.filter((color) => color.slug !== value));

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
            <button
                style="background-color: {selectedItem.oklch};"
                class="size-10 rounded-full ring-2 ring-primary ring-offset-2 transition"
                aria-label={selectedItem.label}
                aria-pressed={true}
                onclick={() => select(selectedItem.slug)}
                type="button">
            </button>
        {:else}
            <div
                class="size-10 rounded-full border border-dashed border-base-content/20"
                aria-hidden="true">
            </div>
        {/if}

        {#each visible as color (color.slug)}
            <button
                style="background-color: {color.oklch};"
                class="size-10 rounded-full transition hover:scale-110"
                class:ring-2={value === color.slug}
                class:ring-offset-2={value === color.slug}
                class:ring-primary={value === color.slug}
                aria-label={color.label}
                aria-pressed={value === color.slug}
                onclick={() => select(color.slug)}
                type="button">
            </button>
        {/each}

        {#if seeMoreNeeded}
            {#if variant === 'drawer'}
                <Drawer
                    triggerClass="flex size-10 items-center justify-center rounded-full border border-dashed border-base-content/30 text-xs font-semibold text-base-content/60 transition hover:bg-base-200"
                    title="Pilih Warna"
                    bind:open>
                    {#snippet trigger()}
                        +{remaining}
                    {/snippet}

                    {@render fullGrid()}
                </Drawer>
            {:else if variant === 'popover'}
                <Popover
                    class="w-100 p-2"
                    align="end"
                    sideOffset={8}
                    triggerProps={{ 'aria-label': 'See more' }}
                    bind:open>
                    {#snippet trigger()}
                        +{remaining}
                    {/snippet}

                    {@render fullGrid()}
                </Popover>
            {:else}
                <button
                    class="flex size-10 items-center justify-center rounded-full border border-dashed border-base-content/30 text-xs font-semibold text-base-content/60 transition hover:bg-base-200"
                    aria-label="See more"
                    onclick={() => (open = true)}
                    type="button">
                    +{remaining}
                </button>
            {/if}
        {/if}
    </div>
</div>

{#if variant === 'modal'}
    <Modal title="Pilih Warna" bind:open>
        {@render fullGrid()}
    </Modal>
{/if}

{#snippet fullGrid()}
    <ScrollArea rootClass="max-h-[60vh] w-full rounded-lg @container">
        <div class="grid grid-cols-8 gap-2 p-1 @md:grid-cols-10 @xl:grid-cols-12">
            {#each decorationColors as color (color.slug)}
                <button
                    style="background-color: {color.oklch};"
                    class="size-10 rounded-full transition hover:scale-110"
                    class:ring-2={value === color.slug}
                    class:ring-offset-2={value === color.slug}
                    class:ring-primary={value === color.slug}
                    aria-label={color.label}
                    aria-pressed={value === color.slug}
                    onclick={() => select(color.slug)}
                    type="button">
                </button>
            {/each}
        </div>
    </ScrollArea>
{/snippet}
