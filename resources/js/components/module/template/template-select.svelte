<script lang="ts">
    import type {
        ComboboxTriggerContext,
        SelectOption,
    } from '@components/ui/forms/combobox.svelte';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';
    import { Combobox as ComboboxPrimitive } from 'bits-ui';

    import { cn } from '@utilities/shadcn';

    import CurrencyAmount from '@components/data/currency-amount.svelte';
    import DecorationBadge from '@components/module/decoration-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Drawer from '@components/ui/drawer.svelte';
    import Combobox from '@components/ui/forms/combobox.svelte';
    import { inputGroupClasses } from '@components/ui/forms/input.svelte';

    /** The template travels with its option — no lookups needed downstream. */
    interface TemplateOption extends SelectOption {
        template: App.Models.TransactionTemplate;
    }

    interface Props {
        templates: App.Models.TransactionTemplate[];
        /** Only templates of this transaction type are offered. */
        activeType?: App.Enums.TransactionType;
        /** Currently selected source account — matching templates sort first and ride along as context. */
        selectedAccountId?: string | number | null;
        /** Presentation variant, the category-select pattern: chip row, searchable combobox, or drawer list. */
        variant?: 'inline' | 'combobox' | 'drawer';
        placeholder?: string;
        class?: string;
    }

    let {
        templates,
        activeType = undefined,
        selectedAccountId = null,
        variant = 'combobox',
        placeholder = 'Pick a template',
        class: _class,
    }: Props = $props();

    /* Type-filtered; templates whose default source matches the selected account first. */
    const offered = $derived.by(() => {
        const key = String(selectedAccountId ?? '');

        return templates
            .filter((template) => activeType === undefined || template.type === activeType)
            .sort((a, b) => {
                const aMatch = a.account_id !== null && String(a.account_id) === key ? 0 : 1;
                const bMatch = b.account_id !== null && String(b.account_id) === key ? 0 : 1;

                return aMatch - bMatch;
            });
    });

    const comboboxOptions = $derived(
        offered.map((template) => ({
            value: String(template.id),
            label: template.name,
            template,
        }))
    );

    /* Templates are one-shot appliers, not a bound value: picking navigates to the
       server-seeded create form (spec §8) and the picker resets. */
    function apply(template: App.Models.TransactionTemplate): void {
        router.visit(
            TransactionController.create.url({
                query: {
                    template: template.id,
                    ...(selectedAccountId ? { account_id: selectedAccountId } : {}),
                },
            }),
            { preserveScroll: true }
        );
    }

    /** The combobox never holds a selection — its display stays on the placeholder. */
    function getComboboxValue(): string {
        return '';
    }

    function setComboboxValue(next: string): void {
        const template = offered.find((candidate) => String(candidate.id) === next);

        if (template) {
            apply(template);
        }

        comboboxOpen = false;
    }

    let comboboxOpen = $state(false);
    let drawerOpen = $state(false);
</script>

{#if offered.length}
    {#if variant === 'inline'}
        <div class={cn('-mx-5 flex gap-2 overflow-x-auto px-5 pb-1', _class)}>
            {#each offered as template (template.id)}
                <Button
                    class="min-h-12 w-fit p-2"
                    color="light"
                    onclick={() => apply(template)}
                    variant="outline">
                    <DecorationBadge decorations={template.decorations} size="sm" />
                    <span class="text-sm font-medium whitespace-nowrap">{template.name}</span>
                </Button>
            {/each}
        </div>
    {:else if variant === 'combobox'}
        <Combobox
            contentProps={{ sideOffset: 10 }}
            inputProps={{ class: 'placeholder:text-muted-foreground' }}
            {option}
            options={comboboxOptions}
            {placeholder}
            {trigger}
            bind:open={comboboxOpen}
            bind:value={getComboboxValue, setComboboxValue} />
    {:else}
        <Button class={_class} color="light" onclick={() => (drawerOpen = true)} variant="outline">
            <i class="iconify size-4 solar--notes-line-duotone"></i>
            {placeholder}
        </Button>

        <Drawer bind:open={drawerOpen}>
            <div class="space-y-1 p-1">
                {#each offered as template (template.id)}
                    <button
                        class="flex w-full items-center gap-3 rounded-xl px-2 py-2.5 text-left transition-colors hover:bg-card"
                        onclick={() => apply(template)}
                        type="button">
                        <DecorationBadge decorations={template.decorations} />

                        <span class="min-w-0 grow">
                            <span class="block truncate text-sm font-medium">{template.name}</span>
                            <span class="block truncate text-xs text-foreground/70">
                                <CurrencyAmount value={Number(template.amount)} />
                            </span>
                        </span>

                        <i
                            class="iconify size-5 shrink-0 text-neutral solar--alt-arrow-right-linear"
                            aria-hidden="true"></i>
                    </button>
                {/each}
            </div>
        </Drawer>
    {/if}
{/if}

{#snippet option({ template }: TemplateOption)}
    <div class="flex w-full min-w-0 items-center gap-2.5 pr-5">
        <DecorationBadge decorations={template.decorations} size="sm" />
        <span class="truncate text-sm font-medium">{template.name}</span>
        <span class="ml-auto text-xs whitespace-nowrap text-muted-foreground">
            <CurrencyAmount value={Number(template.amount)} />
        </span>
    </div>
{/snippet}

{#snippet trigger({ inputProps, triggerProps }: ComboboxTriggerContext<TemplateOption>)}
    <ComboboxPrimitive.Trigger
        {...triggerProps}
        class={cn(inputGroupClasses, 'relative px-3 hover:bg-foreground/5', _class)}>
        <ComboboxPrimitive.Input
            {...inputProps}
            class="h-full w-full bg-transparent text-sm font-medium outline-none placeholder:text-foreground/70"
            aria-label={placeholder}
            autocomplete="off" />

        <i
            class="pointer-events-none absolute top-1/2 right-2.5 iconify flex size-4 -translate-y-1/2 items-center text-foreground solar--alt-arrow-down-linear"
            class:rotate-180={comboboxOpen}></i>
    </ComboboxPrimitive.Trigger>
{/snippet}
