<script lang="ts" module>
    import { type ButtonProps } from '../button.svelte';

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

    import Button from '../button.svelte';
    import Tooltip from '../tooltip.svelte';

    import { twMerge } from 'tailwind-merge';

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

            <div class="dropdown dropdown-end">
                <Tooltip>
                    {#snippet trigger({ props })}
                        <Button {...props} class="size-8 p-1" color="secondary" variant="outline">
                            <i class="iconify solar--menu-dots-bold-duotone"></i>
                        </Button>
                    {/snippet}
                    Lainnya
                </Tooltip>

                <ul
                    class="menu dropdown-content z-50 mt-2 w-40 rounded-box border border-base-300 bg-base-100 p-2 shadow-sm">
                    {#each actions.slice(2) as action, i (i)}
                        {@const actionIcon =
                            action?.icon ??
                            (action?.type === 'detail'
                                ? 'solar--info-circle-line-duotone'
                                : undefined)}
                        <li>
                            <Button class="justify-start px-2" color="secondary" variant="ghost">
                                <i class={twMerge('iconify', actionIcon)}></i>
                                {action.label}
                            </Button>
                        </li>
                    {/each}
                </ul>
            </div>
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
                useRouter={action?.href?.length > 0}
                variant="outline"
                {...buttonProps}>
                <svelte:element
                    this={icon && 'i'}
                    class={twMerge('iconify stroke-current text-current', icon)} />
            </Button>
        {/snippet}
        {action.label}
    </Tooltip>
{/snippet}

{#snippet DropdownVariant()}{/snippet}
