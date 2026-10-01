<script lang="ts" module>
    export interface SelectOption {
        value: string | number;
        label: string;
        disabled?: boolean;
    }

    export interface SelectInputProps {
        options: SelectOption[];
        value?: string;
        name?: string;
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
        placeholder = 'Select...',
        disabled = false,
        required = false,
        loading = false,
        invalid = false,
        class: className,
    }: SelectInputProps = $props();

    const selectedLabel = $derived(options.find((o) => String(o.value) === String(value))?.label);
</script>

{#if loading}
    <Skeleton class="h-8 w-full {className ?? ''}" />
{:else}
    <Select type="single" bind:value {name} {disabled} {required}>
        <SelectTrigger class="w-full {className ?? ''}" aria-invalid={invalid}>
            {#if selectedLabel}
                {selectedLabel}
            {:else}
                <span class="text-muted-foreground">{placeholder}</span>
            {/if}
        </SelectTrigger>
        <SelectContent>
            {#each options as opt (opt.value)}
                <SelectItem value={String(opt.value)} disabled={opt.disabled}>
                    {opt.label}
                </SelectItem>
            {/each}
        </SelectContent>
    </Select>
{/if}
