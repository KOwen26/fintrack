<script lang="ts">
    import type { RestProps } from '@type/index';
    import type { Snippet } from 'svelte';

    import { twMerge } from 'tailwind-merge';

    interface Props extends RestProps {
        title: string;
        description?: string;
        actions?: Snippet;
    }

    const { title, description, actions, children, ...props }: Props = $props();
</script>

<header class="flex flex-col items-center gap-5 md:flex-row md:justify-between">
    <div>
        <h1 class="text-xl font-bold">{title}</h1>

        <p class={twMerge(['mt-1 text-sm font-medium text-current/80', !description && 'hidden'])}>
            {description}
        </p>

        {@render children?.()}
    </div>
    <div>
        {@render actions?.()}
    </div>
</header>
