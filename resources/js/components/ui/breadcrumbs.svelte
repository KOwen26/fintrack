<script lang="ts" module>
    import type { Snippet } from 'svelte';

    export type BreadcrumbItem = {
        class?: string;
        href?: string;
        title: string;
        group?: string;
        icon?: string | Snippet;
    };

    export type BreadcrumbProps = RestProps & {
        ref?: HTMLElement | null;
        items: Array<BreadcrumbItem>;
        separator?: Snippet;
        separatorClass?: string;
        children?: Snippet<
            [
                {
                    items: Array<BreadcrumbItem | Array<BreadcrumbItem>>;
                    breadcrumbItem: Snippet<[BreadcrumbItem]>;
                    breadcrumbSeparator: Snippet<[BreadcrumbItem]>;
                },
            ]
        >;
    };
</script>

<script lang="ts">
    import type { RestProps } from '@type/index';

    import Popover from './popover.svelte';

    import { inertia } from '@inertiajs/svelte';

    import { cn } from '@utilities/shadcn.js';

    let elementRef: HTMLElement | null = null;
    const maxShownLength = 3;

    let {
        items: _items,
        separator,
        separatorClass = '',

        ref = $bindable(elementRef),
        class: className,
        children,
        ...props
    }: BreadcrumbProps = $props();

    const items = $derived(
        _items.length > maxShownLength
            ? _items.map((item, index) => {
                  return {
                      ...item,
                      group: item.group?.length
                          ? item.group
                          : index > 0 && index < _items.length - (maxShownLength - 1)
                            ? 'collapsed'
                            : undefined,
                  };
              })
            : _items
    );

    const groupedItems = $derived(
        items
            .reduce((acc, cur) => {
                if (cur?.group?.length) {
                    const groupIndex = acc.findIndex((group) => group[0].group === cur.group);
                    if (groupIndex === -1) {
                        acc.push([cur]);
                    } else {
                        acc[groupIndex].push(cur);
                    }
                } else {
                    acc.push([cur]);
                }

                return acc;
            }, [])
            .map((group) => (group.length === 1 ? group[0] : group))
    );
</script>

<nav bind:this={elementRef} aria-label="breadcrumb" data-slot="breadcrumb">
    <ol
        class={cn(
            'text-muted-foreground flex flex-wrap items-center gap-1 text-sm wrap-break-word',
            className
        )}
        data-slot="breadcrumb-list"
        {...props}>
        {#if typeof children === 'function'}
            {@render children({ items, breadcrumbItem, breadcrumbSeparator })}
        {:else}
            {#each groupedItems as item (item)}
                {#if Array.isArray(item)}
                    {@render breadcrumbCollapsedItems(item)}
                {:else}
                    {@render breadcrumbItem(item)}
                {/if}
                {@render breadcrumbSeparator()}
            {/each}
        {/if}
    </ol>
</nav>

{#snippet breadcrumbItem(item: BreadcrumbItem)}
    {@const hasLink = item?.href?.length > 0}
    <li class={cn('inline-flex items-center gap-1.5', item.class)} data-slot="breadcrumb-item">
        {#if hasLink}
            {@render breadcrumbLink(item)}
        {:else}
            {@render breadcrumbPage(item)}
        {/if}
    </li>
{/snippet}

{#snippet breadcrumbLink(item: BreadcrumbItem)}
    <a
        class={cn('hover:text-primary-800 transition-colors')}
        data-slot="breadcrumb-link"
        href={item.href}
        use:inertia>
        {@render breadcrumbIcon(item.icon)}
        {item.title}
    </a>
{/snippet}

{#snippet breadcrumbPage(item: BreadcrumbItem)}
    <span
        class={cn('text-primary-600 font-normal')}
        aria-current="page"
        aria-disabled="true"
        data-slot="breadcrumb-page"
        role="link">
        {@render breadcrumbIcon(item.icon)}
        {item.title}
    </span>
{/snippet}

{#snippet breadcrumbSeparator()}
    <li
        class={cn('size-5 leading-none last-of-type:hidden [&>svg]:size-3.5', separatorClass)}
        aria-hidden="true"
        data-slot="breadcrumb-separator"
        role="presentation">
        {#if typeof separator === 'function'}
            {@render separator?.()}
        {:else}
            <i class="iconify tabler--chevron-right"></i>
        {/if}
    </li>
{/snippet}

{#snippet breadcrumbCollapsedItems(items: BreadcrumbItem[])}
    <li data-slot="breadcrumb-collapsed-items">
        <Popover class="w-40 p-2" sideOffset={8}>
            {#snippet trigger()}
                <i class="iconify ph--dots-three-duotone size-4"></i>
                <span class="sr-only">More</span>
            {/snippet}

            <ul data-slot="breadcrumb-collapsed-items-content">
                {#each items as item (item)}
                    <li
                        class={cn('hover:bg-accent rounded-sm px-2 py-1', item.class)}
                        data-slot="breadcrumb-collapsed-item">
                        {#if item.href}
                            {@render breadcrumbLink(item)}
                        {:else}
                            {@render breadcrumbPage(item)}
                        {/if}
                    </li>
                {/each}
            </ul>
        </Popover>
    </li>
{/snippet}

{#snippet breadcrumbIcon(icon: BreadcrumbItem['icon'])}
    {#if icon}
        <span class="ml-1" data-slot="breadcrumb-icon">
            {#if typeof icon === 'function'}
                {@render icon()}
            {:else}
                <i class={['', icon]}></i>
            {/if}
        </span>
    {/if}
{/snippet}
