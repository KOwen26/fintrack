<script lang="ts">
    import type { RestProps } from '@type/index';

    import Input from './input.svelte';

    import { cn } from '@utilities/shadcn';

    interface Props extends RestProps {
        value?: string;
        placeholder?: string;
        oninput?: (value: string) => void;
        onclear?: () => void;
        class?: string;
    }

    let {
        value = $bindable(''),
        placeholder = 'Search...',
        oninput,
        onclear,
        class: _class,
        ...rest
    }: Props = $props();
</script>

<div class={cn('relative flex items-center', _class)}>
    <i
        class="pointer-events-none absolute left-3 iconify size-4 text-foreground/70 solar--magnifer-line-duotone"
        aria-hidden="true"></i>
    <Input
        class="px-9"
        oninput={(e) => oninput?.(e.currentTarget.value ?? '')}
        {placeholder}
        type="text"
        bind:value
        {...rest} />
    {#if value}
        <button
            class="absolute right-2 flex size-6 items-center justify-center rounded text-foreground/70 transition-colors hover:text-foreground"
            aria-label="Clear search"
            onclick={() => {
                value = '';
                onclear?.();
            }}
            type="button">
            <i class="iconify size-4 solar--close-linear"></i>
        </button>
    {/if}
</div>
