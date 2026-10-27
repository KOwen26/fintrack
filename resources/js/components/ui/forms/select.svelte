<script lang="ts" module>
    import type { ComponentProps } from 'svelte';

    export interface SelectOption {
        value: string | number;
        label: string;
        disabled?: boolean;
    }

    export interface SelectProps {
        options: SelectOption[];
        value?: string;
        name?: string;
        type?: ComponentProps<typeof Select>['type'];
        placeholder?: string;
        disabled?: boolean;
        required?: boolean;
        loading?: boolean;
        invalid?: boolean;
        class?: string;
    }
</script>

<script lang="ts">
    import { Select, SelectContent, SelectItem, SelectTrigger } from '@components/ui/forms/select';
    import Skeleton from '@components/ui/skeleton.svelte';

    let {
        options,
        value = $bindable(),
        name,
        type = 'single',
        placeholder = 'Select...',
        loading = false,
        invalid = false,
        class: className,
        ...props
    }: SelectProps = $props();

    const selectedLabel = $derived(options?.find((o) => String(o.value) === String(value))?.label);
</script>

{#if loading}
    <Skeleton class="h-8 w-full {className ?? ''}" />
{:else}
    <Select {name} {type} bind:value {...props}>
        <SelectTrigger class="w-full {className ?? ''}" aria-invalid={invalid}>
            {#if selectedLabel}
                {selectedLabel}
            {:else}
                <span class="text-muted-foreground">{placeholder}</span>
            {/if}
        </SelectTrigger>
        <SelectContent>
            {#each options as opt (opt.value)}
                <SelectItem disabled={opt.disabled} value={String(opt.value)}>
                    {opt.label}
                </SelectItem>
            {/each}
        </SelectContent>
    </Select>
{/if}
