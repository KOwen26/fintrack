<script lang="ts" module>
    import type { ComboboxTriggerProps, WithoutChildrenOrChild } from 'bits-ui';
    import type { Snippet } from 'svelte';

    export interface SelectOption {
        value: string;
        label: string;
        disabled?: boolean;
    }

    /** Context handed to the custom `trigger` snippet. */
    export interface ComboboxTriggerContext<T extends SelectOption = SelectOption> {
        /** Props to spread on the element hosting the search input (carries the wrapper's oninput/onkeydown). */
        inputProps: WithoutChildrenOrChild<Combobox.InputProps>;
        /** Props to spread on a button that toggles the dropdown. */
        triggerProps: WithoutChildrenOrChild<ComboboxTriggerProps>;
        /** The currently selected option, if any. */
        selected: T | undefined;
        /** Dropdown open state. */
        open: boolean;
    }

    export type ComboboxProps<T extends SelectOption = SelectOption> = Omit<
        Combobox.RootProps,
        'items' | 'type'
    > & {
        value: any;
        sideTrigger?: boolean;
        options?: T[] | string;
        multiple?: boolean;
        placeholder?: string;
        inputProps?: WithoutChildrenOrChild<Combobox.InputProps>;
        contentProps?: WithoutChildrenOrChild<Combobox.ContentProps>;
        /** Custom row rendering; receives the option. Defaults to the option label. */
        option?: Snippet<[T]>;
        /** Replaces the built-in trigger layout entirely. The wrapper still owns filtering, items, and value. */
        trigger?: Snippet<[ComboboxTriggerContext<T>]>;
        /** Shows a button that resets the value when a selection is present. */
        clearable?: boolean;
        /** Fired when the value is reset via the clear button. */
        onClear?: () => void;
        /** Behaves like `onValueChange`, but receives the matching option(s) instead of the raw value. */
        onSelected?: (item: T | T[] | undefined) => void;
    };
</script>

<script generics="T extends SelectOption" lang="ts">
    import { useHttp } from '@inertiajs/svelte';
    import { Combobox, mergeProps } from 'bits-ui';
    import { onMount } from 'svelte';

    import { cn } from '@utilities/shadcn';

    let {
        value = $bindable(),
        open = $bindable(false),
        sideTrigger = false,
        options,
        multiple = false,
        placeholder = 'Cari...',
        inputProps,
        contentProps,
        option,
        trigger,
        disabled,
        clearable = false,
        onClear,
        onSelected,
        ...restProps
    }: ComboboxProps<T> = $props();

    let inputRef = $state<HTMLInputElement | null>(null);

    const type = $derived(multiple ? 'multiple' : 'single');

    let searchValue = $state('');
    let fetchedItems = $state<T[]>([]);

    const resolvedItems = $derived(typeof options === 'string' ? fetchedItems : (options ?? []));

    function findItem(val: string | number | undefined): T | undefined {
        if (val === undefined) {
            return undefined;
        }

        return resolvedItems?.find((item) => String(item.value) === String(val));
    }

    const label = $derived(findItem(Array.isArray(value) ? undefined : value)?.label ?? '');

    const filteredItems = $derived.by(() => {
        if (!searchValue?.length) {
            return resolvedItems;
        }

        return resolvedItems?.filter((item) =>
            item.label.toLowerCase().includes(searchValue.toLowerCase())
        );
    });

    const selectedItems = $derived.by<T[]>(() => {
        if (type !== 'multiple' || !Array.isArray(value)) {
            return [];
        }

        return value.map((v) => findItem(v)).filter((item): item is T => item !== undefined);
    });

    const hasValue = $derived(Array.isArray(value) ? value.length > 0 : !!value);

    function removeValue(val: string | number) {
        if (!Array.isArray(value)) {
            return;
        }

        value = value?.filter((v) => String(v) !== String(val));
    }

    function clear(e: MouseEvent) {
        e.stopPropagation();
        e.preventDefault();

        value = multiple ? [] : '';
        onClear?.();
    }

    function handleInput(e: Event & { currentTarget: HTMLInputElement }) {
        searchValue = e.currentTarget.value;
    }

    function handleInputKeydown(e: KeyboardEvent) {
        if (e.code === 'Space') {
            e.stopPropagation();
        }
    }

    function handleOpenChange(newOpen: boolean) {
        if (!newOpen) {
            searchValue = '';
        }
    }

    function handleSelected(newValue: string | string[]) {
        if (multiple) {
            searchValue = '';
        }

        if (!onSelected) {
            return;
        }

        if (Array.isArray(newValue)) {
            onSelected(
                newValue.map((v) => findItem(v)).filter((item): item is T => item !== undefined)
            );

            return;
        }

        onSelected(findItem(newValue));
    }

    const http = useHttp<Record<string, any>, { data: T[] }>({});

    function fetchItems() {
        if (typeof options !== 'string') {
            return;
        }

        // Payloads can't survive serialization — endpoint mode degrades to the base shape.
        http.get(options, {
            onSuccess: ({ data }) => {
                fetchedItems = data as T[];
            },
            onError: () => {
                fetchedItems = [];
            },
        });
    }

    onMount(() => {
        fetchItems();
    });

    // Sync the input's displayed text when value changes externally (programmatic reset or
    // initial value after async items load). Bits UI only updates the input via selection
    // events — setting `value` programmatically leaves the display stale without this.
    $effect(() => {
        if (!inputRef || open || multiple) {
            return;
        }

        inputRef.value = label;
    });

    const mergedRootProps = $derived(
        mergeProps(restProps, {
            onOpenChangeComplete: handleOpenChange,
            onValueChange: handleSelected,
        })
    );
    const mergedInputProps = $derived(
        mergeProps(inputProps, { oninput: handleInput, onkeydown: handleInputKeydown })
    );

    const mergedContentProps = $derived(
        mergeProps(
            {
                side: 'bottom' as const,
                sideOffset: 4,
                style: '--bits-combobox-content-available-height: 40svh;',
            },
            contentProps
        )
    );

    const selectedOption = $derived(findItem(Array.isArray(value) ? value[0] : value));

    const triggerContext = $derived({
        inputProps: mergeProps(
            { placeholder, disabled },
            mergedInputProps
        ) as WithoutChildrenOrChild<Combobox.InputProps>,
        triggerProps: { disabled } as WithoutChildrenOrChild<ComboboxTriggerProps>,
        selected: selectedOption,
        open,
    });

    const multiWrapperClass =
        'border-input dark:bg-input/30 focus-within:border-ring focus-within:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive aria-invalid:ring-3 flex min-h-8 w-full flex-wrap items-center gap-1 rounded-lg border bg-transparent px-2.5 py-1 text-sm transition-colors focus-within:ring-3';

    const singleInputBaseClass =
        'dark:bg-input/30 border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive aria-invalid:ring-3 disabled:bg-input/50 dark:disabled:bg-input/80 h-8 w-full min-w-0 rounded-lg border bg-transparent px-2.5 py-1 text-base outline-none transition-colors focus-visible:ring-3 md:text-sm placeholder:text-muted-foreground disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50';
</script>

<Combobox.Root {disabled} items={resolvedItems} {type} bind:value bind:open {...mergedRootProps}>
    {#if trigger}
        {@render trigger(triggerContext)}
    {:else if sideTrigger}
        {@render InputBesideTrigger()}
    {:else}
        {@render InputInsideTrigger()}
    {/if}

    <Combobox.Portal>
        <Combobox.Content
            data-slot="combobox-content"
            {...mergedContentProps}
            class={cn(
                'z-50 max-h-(--bits-combobox-content-available-height) w-(--bits-combobox-anchor-width) min-w-(--bits-combobox-anchor-width) overflow-x-hidden overflow-y-auto rounded-lg bg-popover text-popover-foreground shadow-md ring-1 ring-foreground/10 duration-100 data-closed:animate-out data-closed:overflow-hidden data-closed:fade-out-0 data-closed:zoom-out-95 data-open:animate-in data-open:fade-in-0 data-open:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
                contentProps?.class
            )}>
            <Combobox.Viewport class="scroll-my-1 p-1">
                {#each filteredItems as item, i (i)}
                    <Combobox.Item
                        class="relative flex w-full cursor-default items-center gap-1.5 rounded-md py-1 pr-6 pl-1.5 text-sm outline-hidden select-none data-disabled:pointer-events-none data-disabled:opacity-50 data-highlighted:bg-accent data-highlighted:text-accent-foreground"
                        disabled={item.disabled}
                        label={item.label}
                        value={item.value}>
                        {#snippet children({ selected })}
                            {#if option}
                                {@render option(item)}
                            {:else}
                                <span class="truncate">{item.label}</span>
                            {/if}
                            {#if selected}
                                <span
                                    class="absolute end-2 flex size-3.5 items-center justify-center">
                                    <i class="iconify size-3.5 solar--unread-outline"></i>
                                </span>
                            {/if}
                        {/snippet}
                    </Combobox.Item>
                {:else}
                    <span class="block px-1.5 py-1 text-sm text-muted-foreground"
                        >No results found</span>
                {/each}
            </Combobox.Viewport>
        </Combobox.Content>
    </Combobox.Portal>
</Combobox.Root>

{#snippet InputInsideTrigger()}
    <Combobox.Trigger class="w-full">
        {#if multiple}
            <div class={multiWrapperClass}>
                {@render MultipleValueChips()}

                <Combobox.Input
                    class="flex min-w-20 bg-transparent outline-none placeholder:text-muted-foreground"
                    autocomplete="off"
                    placeholder={selectedItems.length === 0 ? placeholder : undefined}
                    bind:ref={inputRef}
                    {...mergedInputProps}>
                    {#snippet child({ props })}
                        <input {...props} value={searchValue} />
                    {/snippet}
                </Combobox.Input>

                {@render ClearButton()}
            </div>
        {:else}
            <div class="relative">
                <Combobox.Input
                    class={cn(singleInputBaseClass, clearable && 'pr-8')}
                    autocomplete="off"
                    {placeholder}
                    bind:ref={inputRef}
                    {...mergedInputProps} />
                {@render ClearButton()}
            </div>
        {/if}
    </Combobox.Trigger>
{/snippet}

{#snippet InputBesideTrigger()}
    {#if multiple}
        <div class={multiWrapperClass}>
            {@render MultipleValueChips()}

            <Combobox.Input
                class="min-w-20 flex-1 bg-transparent outline-none placeholder:text-muted-foreground"
                autocomplete="off"
                placeholder={selectedItems.length === 0 ? placeholder : undefined}
                bind:ref={inputRef}
                {...mergedInputProps}>
                {#snippet child({ props })}
                    <input {...props} value={searchValue} />
                {/snippet}
            </Combobox.Input>

            {@render ClearButton()}

            <Combobox.Trigger
                class="ml-auto shrink-0 text-muted-foreground hover:text-foreground disabled:opacity-50">
                <i class="iconify size-4 solar--alt-arrow-down-outline"></i>
            </Combobox.Trigger>
        </div>
    {:else}
        <div class="relative">
            <Combobox.Input
                class={cn(singleInputBaseClass, 'pr-8', clearable && 'pr-16')}
                autocomplete="off"
                {placeholder}
                bind:ref={inputRef}
                {...mergedInputProps} />

            {@render ClearButton()}

            <Combobox.Trigger
                class="absolute end-0 top-0 flex h-8 w-8 items-center justify-center text-muted-foreground hover:text-foreground disabled:opacity-50">
                <i class="iconify size-4 shrink-0 solar--alt-arrow-down-outline"></i>
            </Combobox.Trigger>
        </div>
    {/if}
{/snippet}

{#snippet MultipleValueChips()}
    {#each selectedItems as item, i (i)}
        <span
            class="flex items-center gap-1 rounded-md bg-secondary px-1.5 py-0.5 text-xs text-secondary-foreground">
            {item.label}
            <button
                class="leading-none hover:text-destructive"
                aria-label="Hapus {item.label}"
                onclick={(e) => {
                    e.stopPropagation();
                    removeValue(item.value);
                }}
                type="button">
                <i class="iconify size-3 solar--close-outline"></i>
            </button>
        </span>
    {/each}
{/snippet}

{#snippet ClearButton()}
    {#if clearable && hasValue && !disabled}
        {@const positionClass = multiple
            ? cn('shrink-0', !sideTrigger && 'ml-auto')
            : cn(
                  'absolute top-0 flex h-8 w-8 items-center justify-center',
                  sideTrigger ? 'end-8' : 'end-0'
              )}
        <button
            class={cn('text-muted-foreground hover:text-foreground', positionClass)}
            aria-label="Hapus pilihan"
            onclick={clear}
            onpointerdown={(e) => e.stopPropagation()}
            type="button">
            <i class="iconify size-4 solar--close-outline"></i>
        </button>
    {/if}
{/snippet}
