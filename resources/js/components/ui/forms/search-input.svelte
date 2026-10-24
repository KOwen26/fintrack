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
        inputClass?: string;
    }

    let {
        value = $bindable(''),
        placeholder = 'Search...',
        oninput,
        onclear,
        class: _class,
        inputClass = '',
        ...props
    }: Props = $props();
</script>

<div class={cn('relative flex items-center', _class)}>
    <i
        class="pointer-events-none absolute left-3 iconify size-4 text-foreground/70 solar--magnifer-line-duotone"
        aria-hidden="true"></i>
    <Input
        class="px-9 {inputClass} text-sm"
        oninput={(e) => oninput?.(e.currentTarget.value ?? '')}
        {placeholder}
        type="text"
        bind:value
        {...props} />
    {#if value}
        <button
            class="absolute right-3 flex size-5 items-center justify-center rounded text-foreground/70 transition-colors hover:text-foreground"
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
