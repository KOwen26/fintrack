<script lang="ts">
    import type { RestProps } from '@type/index';

    import ResponsiveCard from './responsive-card.svelte';

    import ToggleableGrid from '@components/data/toggleable-grid.svelte';

    interface Props extends RestProps {
        id?: string;
        mode?: 'list' | 'grid';
    }

    let { id: _id, mode = $bindable('grid'), children, ...props }: Props = $props();

    let cid = $props.id();
    let id = $derived(`toggleable-card-${_id ?? cid}`);
    // Todo save mode to session storage based on the `'toggleable_card'_base64(user_id): { lowercased_id: mode }`
</script>

<ResponsiveCard wrapperProps={{ id }} {...props}>
    {#snippet headerAction()}
        <ToggleableGrid bind:mode />
    {/snippet}

    {@render children?.()}
</ResponsiveCard>
