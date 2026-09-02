<script lang="ts">
    import type { Snippet } from 'svelte';

    import { cn } from '@utilities/shadcn';

    interface Props {
        variant?: 'default' | 'skeleton' | 'create';

        /** Hero surface colors — applied to the body area (default variant). */
        background?: string;
        textColor?: string;

        /** Rendered below the body area, outside its min-height. */
        footer?: Snippet;

        /** Extra classes for the body area (layout, padding). */
        bodyClass?: string;

        class?: string;
        children?: Snippet;
    }

    let {
        variant = 'default',
        background,
        textColor: color,
        footer,
        bodyClass,
        class: _class,
        children,
    }: Props = $props();

    const shellClass = $derived(
        cn(
            'overflow-hidden rounded-lg shadow-xs',
            variant === 'create' &&
                'cursor-pointer border-2 border-dashed border-base-200 bg-card shadow-none transition-colors hover:border-primary/50 hover:bg-primary/5',
            _class
        )
    );

    const resolvedBodyClass = $derived(
        cn(
            'relative min-h-36 md:min-h-40',
            variant === 'skeleton' && 'bg-card',
            variant === 'create' && 'flex items-center justify-center',
            bodyClass
        )
    );
</script>

<div class={shellClass}>
    <div style:background style:color class={resolvedBodyClass}>
        {#if variant === 'skeleton'}
            {@render SkeletonContent()}
        {:else if variant === 'create' && !children}
            {@render CreateContent()}
        {:else}
            {@render children?.()}
        {/if}
    </div>

    {#if footer}
        {@render footer()}
    {/if}
</div>

<!-- Default create content — call-to-action inviting account creation -->
{#snippet CreateContent()}
    <div class="text-center">
        <i class="mx-auto mb-1 iconify block size-5 text-base-content/50 solar--add-bold-duotone"
        ></i>
        <span class="text-sm font-medium text-base-content/50">Add Account</span>
    </div>
{/snippet}

<!-- Mirrors the account card layout: header row, balance, quick actions -->
{#snippet SkeletonContent()}
    <div class="flex h-full animate-pulse flex-col justify-around gap-3 p-3 md:p-6">
        <div class="flex items-center gap-2">
            <div class="size-8 shrink-0 rounded-lg bg-base-content/10 md:size-9"></div>
            <div class="flex-1 space-y-2">
                <div class="h-3.5 w-28 rounded bg-base-content/10"></div>
                <div class="h-2.5 w-20 rounded bg-base-content/10"></div>
            </div>
        </div>

        <div class="space-y-2">
            <div class="h-3 w-24 rounded bg-base-content/10"></div>
            <div class="h-8 w-44 rounded bg-base-content/10 md:h-10 lg:h-12"></div>
        </div>

        <div class="flex gap-2">
            <div class="h-9 w-20 rounded-lg bg-base-content/10"></div>
            <div class="h-9 w-24 rounded-lg bg-base-content/10"></div>
            <div class="h-9 w-20 rounded-lg bg-base-content/10"></div>
        </div>
    </div>
{/snippet}
