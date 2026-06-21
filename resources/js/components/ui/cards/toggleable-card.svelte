<script lang="ts">
    import type { RestProps } from '@type/index';

    import Button from '../button.svelte';

    import { cn } from 'tailwind-variants';

    import Card from '@components/ui/card.svelte';

    interface Props extends RestProps {
        id?: string;
        mode?: 'list' | 'grid';
    }

    let { id: _id, mode = $bindable('list'), children, ...props }: Props = $props();

    let cid = $props.id();
    let id = $derived(`toggleable-card-${_id ?? cid}`);
    // Todo save mode to session storage based on the `'toggleable_card'_base64(user_id): { lowercased_id: mode }`
</script>

<Card
    {...props}
    wrapperProps={{ id }}
    titleClass="grow"
    wrapperClass="p-4 md:p-6 border-y rounded-none">
    {#snippet headerAction()}
        <div class="join border rounded overflow-clip">
            <Button
                class={cn('size-8 p-1', mode === 'list' ? 'btn-active' : '')}
                color="light"
                variant="ghost"
                onclick={() => (mode = 'list')}>
                <i class="iconify size-6 ph--list-bold"></i>
            </Button>

            <Button
                class={cn('size-8 p-1', mode === 'grid' ? 'btn-active' : '')}
                color="light"
                variant="ghost"
                onclick={() => (mode = 'grid')}>
                <i class="iconify size-6 ph--text-columns-bold"></i>
            </Button>
        </div>
    {/snippet}

    {@render children?.()}
</Card>
