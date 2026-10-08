<script lang="ts" module>
    import { type ButtonProps } from '@components/ui/button.svelte';

    type ButtonActionProps = Pick<ButtonProps, 'color' | 'href' | 'onclick'>;

    export type RowAction = (
        | {
              type: 'detail';
              label: string;
              icon?: string;
          }
        | {
              type: 'action';
              label: string;
              icon: string;
          }
    ) &
        Partial<ButtonActionProps>;
</script>

<script lang="ts">
    import type { RestProps } from '@type/index';

    import { twMerge } from 'tailwind-merge';

    import * as DropdownMenu from '@components/ui/atoms/dropdown-menu';
    import Button from '@components/ui/button.svelte';
    import Tooltip from '@components/ui/tooltip.svelte';

    interface Props extends RestProps {
        variant?: 'button' | 'dropdown';
        actions: RowAction[];
    }

    let { variant = 'button', actions }: Props = $props();
</script>

<div>
    {#if variant === 'button'}
        {@render ButtonVariant(actions)}
    {:else if variant === 'dropdown'}
        {@render DropdownVariant()}
    {/if}
</div>

{#snippet ButtonVariant(actions: RowAction[])}
    <div class="flex flex-row justify-center gap-2.5">
        {#if actions.length <= 3}
            {#each actions as action, i (i)}
                {@render ActionButton(action)}
            {/each}
        {:else}
            {#each actions.slice(0, 2) as action, i (i)}
                {@render ActionButton(action)}
            {/each}

            <DropdownMenu.Root>
                <Tooltip>
                    {#snippet trigger({ props: tooltipProps })}
                        <DropdownMenu.Trigger>
                            {#snippet child({ props: triggerProps })}
                                <Button
                                    {...tooltipProps}
                                    {...triggerProps}
                                    class="size-8 p-1"
                                    color="secondary"
                                    variant="outline">
                                    <i class="iconify solar--menu-dots-bold-duotone"></i>
                                </Button>
                            {/snippet}
                        </DropdownMenu.Trigger>
                    {/snippet}
                    Lainnya
                </Tooltip>

                <DropdownMenu.Content align="end" class="w-40 rounded-lg">
                    {#each actions.slice(2) as action, i (i)}
                        {@const actionIcon =
                            action?.icon ??
                            (action?.type === 'detail'
                                ? 'solar--info-circle-line-duotone'
                                : undefined)}
                        <DropdownMenu.Item>
                            <Button
                                class="w-full justify-start px-2"
                                color="secondary"
                                variant="ghost">
                                <i class={twMerge('iconify', actionIcon)}></i>
                                {action.label}
                            </Button>
                        </DropdownMenu.Item>
                    {/each}
                </DropdownMenu.Content>
            </DropdownMenu.Root>
        {/if}
    </div>
{/snippet}

{#snippet ActionButton(action: RowAction)}
    {@const { type, label, icon: _icon, ...buttonProps } = action}
    {@const icon = _icon ?? (type === 'detail' ? 'solar--info-circle-line-duotone' : undefined)}
    <Tooltip>
        {#snippet trigger({ props })}
            <Button
                {...props}
                class="size-8 p-1"
                color={type === 'action' ? 'secondary' : 'info'}
                useRouter={Boolean(action.href)}
                variant="outline"
                {...buttonProps}>
                <svelte:element
                    this={icon && 'i'}
                    class={twMerge('iconify stroke-current text-current', icon)} />
            </Button>
        {/snippet}
        {label}
    </Tooltip>
{/snippet}

{#snippet DropdownVariant()}{/snippet}
