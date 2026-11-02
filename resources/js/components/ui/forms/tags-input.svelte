<script lang="ts">
    import type {
        ComboboxTriggerContext,
        SelectOption,
    } from '@components/ui/forms/combobox.svelte';

    import { Combobox as ComboboxPrimitive } from 'bits-ui';

    import { MAX_TAGS, normalizeTag } from '@schema/tags.schema';

    import StringHelper from '@utilities/string-helper';

    import Combobox from '@components/ui/forms/combobox.svelte';

    /** Dropdown row — a suggestion slug, or the free-text "Create …" offer. */
    interface TagComboboxOption extends SelectOption {
        create?: boolean;
    }

    interface Props {
        value?: string[];
        tags?: string[];
        placeholder?: string;
        disabled?: boolean;
    }

    let {
        value = $bindable([]),
        tags = [],
        placeholder = 'Add tag…',
        disabled = false,
    }: Props = $props();

    /** Live text in the input — committed only as a normalized slug chip. */
    let draft = $state('');

    const atCapacity = $derived(value.length >= MAX_TAGS);

    /** Unpicked suggestions narrowed by the normalized draft; the cap empties the menu entirely. */
    const suggestionOptions = $derived.by<TagComboboxOption[]>(() => {
        if (atCapacity) {
            return [];
        }

        const needle = normalizeTag(draft);

        return tags
            .filter((slug) => !value.includes(slug))
            .filter((slug) => !needle || slug.includes(needle))
            .map((slug) => ({ value: slug, label: slug }));
    });

    /** Free-text offer — only for a non-empty slug that is neither selected nor an existing suggestion. */
    const createOption = $derived.by<TagComboboxOption | undefined>(() => {
        const slug = normalizeTag(draft);

        if (atCapacity || !slug || value.includes(slug) || tags.includes(slug)) {
            return undefined;
        }

        return { value: slug, label: `Create "${slug}"`, create: true };
    });

    const options = $derived(
        createOption ? [...suggestionOptions, createOption] : suggestionOptions
    );

    function commitDraft(): void {
        const slug = normalizeTag(draft);

        draft = '';

        if (!slug || value.includes(slug) || atCapacity) {
            return;
        }

        value = [...value, slug];
    }

    function removeTag(slug: string): void {
        if (disabled) return;

        value = value.filter((tag) => tag !== slug);
    }

    function removeLastTag(): void {
        if (disabled || !value.length) return;

        value = value.slice(0, -1);
    }

    /** Dropdown selection — the row's slug is already in `value`; just reset the draft. */
    function handleSelected(): void {
        draft = '';
    }

    function handleInput(event: Event & { currentTarget: HTMLInputElement }): void {
        draft = event.currentTarget.value;
    }

    function handleKeydown(event: KeyboardEvent): void {
        if ((event.key === 'Enter' && !event.isComposing) || event.key === ',') {
            event.preventDefault();
            event.stopPropagation();
            commitDraft();

            return;
        }

        // Keep spaces typing into the input — without the stop the event bubbles
        // to the trigger, whose Enter/Space handling would toggle the dropdown.
        if (event.code === 'Space') {
            event.stopPropagation();

            return;
        }

        if (event.key === 'Backspace' && draft === '') {
            event.preventDefault();
            removeLastTag();
        }
    }
</script>

<Combobox
    {disabled}
    multiple
    onSelected={handleSelected}
    {option}
    {options}
    {placeholder}
    {trigger}
    bind:value />

{#snippet option(item: TagComboboxOption)}
    <div class="flex w-full min-w-0 items-center gap-2">
        <i
            class="iconify size-4 shrink-0 text-muted-foreground {item.create
                ? 'solar--add-circle-bold-duotone'
                : 'solar--tag-bold-duotone'}"></i>
        {#if item.create}
            <span class="truncate text-sm">
                Create <span class="font-semibold text-primary">
                    {StringHelper.snakeToTitle(item.value)}
                </span>
            </span>
        {:else}
            <span class="truncate text-sm">{StringHelper.snakeToTitle(item.value)}</span>
        {/if}
    </div>
{/snippet}

{#snippet trigger({ inputProps, triggerProps }: ComboboxTriggerContext<TagComboboxOption>)}
    <ComboboxPrimitive.Trigger
        {...triggerProps}
        class="flex min-h-12 w-full flex-wrap items-center gap-1 rounded-lg border border-input bg-transparent px-2.5 py-1.5 text-sm transition-colors focus-within:border-ring focus-within:ring-3 focus-within:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 md:min-h-10 dark:bg-input/30">
        {#each value as tag (tag)}
            <span
                class="flex shrink-0 items-center gap-1 rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary">
                {StringHelper.snakeToTitle(tag)}
                <button
                    class="flex size-4 shrink-0 items-center justify-center rounded-full transition-colors hover:text-destructive"
                    aria-label="Remove tag {StringHelper.snakeToTitle(tag)}"
                    {disabled}
                    onclick={() => removeTag(tag)}
                    onpointerdown={(event) => event.stopPropagation()}
                    type="button">
                    <i class="iconify size-3 solar--close-linear"></i>
                </button>
            </span>
        {/each}

        <ComboboxPrimitive.Input
            {...inputProps}
            aria-label={placeholder}
            autocomplete="off"
            oninput={handleInput}
            onkeydown={handleKeydown}>
            {#snippet child({ props })}
                <input
                    {...props}
                    class="flex min-w-20 bg-transparent outline-none placeholder:text-muted-foreground"
                    disabled={disabled || atCapacity}
                    value={draft} />
            {/snippet}
        </ComboboxPrimitive.Input>
    </ComboboxPrimitive.Trigger>
{/snippet}
