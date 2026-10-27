<script lang="ts">
    import { getActiveDecorationColors } from '@data/decorations';

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
            {@render item(selectedItem)}
        {:else}
            <div
                class="size-10 rounded-full border border-dashed border-foreground/20"
                aria-hidden="true">
            </div>
        {/if}

        {#each visible as color (color.slug)}
            {@render item(color)}
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

                    {@render fullGrid()}
                </Popover>
            {:else}
                <button
                    class="flex size-10 items-center justify-center rounded-full border border-dashed border-input/50 text-xs font-semibold text-foreground/70 transition hover:bg-card"
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
    <DrawerModal title="Select Color" bind:open>
        <div class="px-4 pb-5">
            {@render fullGrid()}
        </div>
    </DrawerModal>
{/if}

{#snippet item(color)}
    <button
        style="background-color: {color.oklch};"
        class={[
            'size-10 rounded-full transition hover:scale-105',
            value === color.slug ? 'ring-2 ring-primary ring-offset-2' : '',
        ]}
        aria-label={color.label}
        aria-pressed={value === color.slug}
        onclick={() => select(color.slug)}
        type="button">
    </button>
{/snippet}

{#snippet fullGrid()}
    <ScrollArea rootClass="max-h-[60vh] w-full @container">
        <div class="grid grid-cols-8 gap-2 p-1 @md:grid-cols-10 @xl:grid-cols-12">
            {#each decorationColors as color (color.slug)}
                {@render item(color)}
            {/each}
        </div>
    </ScrollArea>
{/snippet}
