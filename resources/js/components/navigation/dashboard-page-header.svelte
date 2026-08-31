<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    import { twMerge } from 'tailwind-merge';

    interface Props extends RestProps {
        title?: string;
        description?: string;
        actions?: Snippet;
    }

    const { title, description, class: _class, actions, children, ...props }: Props = $props();
</script>

<header class={['flex items-center justify-between gap-5 md:flex-row', _class]}>
    {#if !children}
        <div>
            <h1 class="text-xl font-bold">{title}</h1>

            <p
                class={twMerge([
                    'mt-1 text-sm font-medium text-current/80',
                    !description && 'hidden',
                ])}>
                {description}
            </p>
        </div>
    {:else}
        {@render children?.()}
    {/if}

    <div>
        {@render actions?.()}
    </div>
</header>
