<script lang="ts" module>
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';

    import { Accordion as AccordionPrimitive } from 'bits-ui';

    const baseItemClass = 'border-b last:border-b-0';
    const baseTriggerClass =
        'focus-visible:border-ring focus-visible:ring-ring/50 flex flex-1 items-start justify-between gap-4 rounded-md py-4 text-left text-sm font-medium transition-all outline-none hover:underline focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50 [&[data-state=open]>svg]:rotate-180';
    const baseContentClass =
        'data-[state=closed]:animate-accordion-up data-[state=open]:animate-accordion-down overflow-hidden text-sm';
    const baseContentInnerClass = 'pt-0 pb-4';
    const baseChevronClass =
        'text-muted-foreground pointer-events-none size-4 shrink-0 translate-y-0.5 transition-transform duration-200';

    type TriggerProps = {
        class?: string;
        disabled?: boolean;
    };

    export interface AccordionProps extends HTMLAttributes<HTMLDivElement> {
        rootRef?: HTMLElement | null;
        value?: string | string[];
        type?: 'single' | 'multiple';
        collapsible?: boolean;
        disabled?: boolean;
        dir?: 'ltr' | 'rtl';
        items: Array<{
            value: string;
            trigger: string | Snippet<[{ props: TriggerProps }]>;
            content: string | Snippet;
            disabled?: boolean;
            headerLevel?: AccordionPrimitive.HeaderProps['level'];
        }>;
    }
</script>

<script lang="ts">
    let {
        rootRef = $bindable(null),
        value = $bindable(),
        type = 'single',
        collapsible = true,
        disabled,
        dir,
        items,
        ...restProps
    }: AccordionProps = $props();
</script>

<AccordionPrimitive.Root
    data-slot="accordion"
    {dir}
    {disabled}
    {type}
    bind:ref={rootRef}
    bind:value={value as never}
    {...restProps}>
    {#each items as { value, trigger, content, disabled: itemDisabled, headerLevel = 3 } (value)}
        <AccordionPrimitive.Item
            data-slot="accordion-item"
            class={baseItemClass}
            {disabled}
            {value}>
            <AccordionPrimitive.Header class="flex" level={headerLevel}>
                <AccordionPrimitive.Trigger
                    data-slot="accordion-trigger"
                    class={baseTriggerClass}
                    {disabled}>
                    {#if typeof trigger === 'string'}
                        {trigger}
                    {:else}
                        {@render trigger?.({
                            props: {
                                class: baseTriggerClass,
                                disabled: itemDisabled,
                            },
                        })}
                    {/if}
                    <i class="iconify ph--caret-down-duotone {baseChevronClass}"></i>
                </AccordionPrimitive.Trigger>
            </AccordionPrimitive.Header>
            <AccordionPrimitive.Content data-slot="accordion-content" class={baseContentClass}>
                <div class={baseContentInnerClass}>
                    {#if typeof content === 'function'}
                        {@render content?.()}
                    {:else}
                        {content}
                    {/if}
                </div>
            </AccordionPrimitive.Content>
        </AccordionPrimitive.Item>
    {/each}
</AccordionPrimitive.Root>
