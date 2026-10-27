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
</script>

<div
    class={cn(
        '@container/account-card',
        'size-full overflow-hidden rounded-lg shadow-xs',
        variant === 'create' &&
            'cursor-pointer border-2 border-dashed border-border bg-card shadow-none transition-colors hover:border-primary/50 hover:bg-primary/5',
        _class
    )}>
    <div
        style:background
        style:color
        class={cn(
            'relative size-full min-h-24 @xs/account-card:min-h-36 @xs/account-card:md:min-h-40',
            variant === 'skeleton' && 'bg-card',
            variant === 'create' && 'flex items-center justify-center',
            bodyClass
        )}>
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
    <div class="text-center text-muted-foreground">
        <i class="mx-auto mb-1 iconify block size-5 solar--add-line-duotone"></i>
        <span class="text-sm font-medium">Add Account</span>
    </div>
{/snippet}

<!-- Mirrors the account card layout: header row, balance, quick actions -->
{#snippet SkeletonContent()}
    <div class="flex h-full animate-pulse flex-col justify-around gap-3 p-3 md:p-6">
        <div class="flex items-center gap-2">
            <div class="size-8 shrink-0 rounded-lg bg-muted md:size-9"></div>
            <div class="flex-1 space-y-2">
                <div class="h-3.5 w-28 rounded bg-muted"></div>
                <div class="h-2.5 w-20 rounded bg-muted"></div>
            </div>
        </div>

        <div class="space-y-2">
            <div class="h-3 w-24 rounded bg-muted"></div>
            <div class="h-8 w-44 rounded bg-muted md:h-10 lg:h-12"></div>
        </div>

        <div class="flex gap-2">
            <div class="h-9 w-20 rounded-lg bg-muted"></div>
            <div class="h-9 w-24 rounded-lg bg-muted"></div>
            <div class="h-9 w-20 rounded-lg bg-muted"></div>
        </div>
    </div>
{/snippet}
