<script lang="ts">
    import type { RestProps } from '@type/index';

    import { cn } from '@utilities/shadcn';

    import Icon from '@assets/images/icon-transparent.png';

    let { class: _class }: RestProps = $props();
</script>

<img class={cn('aspect-square size-10', _class)} alt="Logo" src={Icon} />
