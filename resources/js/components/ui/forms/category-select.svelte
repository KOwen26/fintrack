<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { page } from '@inertiajs/svelte';
    import Svelecte from 'svelecte';

    import { cn } from '@utilities/shadcn';

    interface Props {
        value?: string | number;
        categories?: App.Models.Category[];
        placeholder?: string;
        class?: string;
    }

    let {
        value = $bindable(),
        categories = [],
        placeholder = 'Select category',
        class: _class,
        ...props
    }: Props = $props();

    const defaultCategories = $derived(page.props?.static?.groupedCategories);

    const options = $derived(categories?.length ? categories : defaultCategories);
</script>

<Svelecte
    class={cn('w-full', _class)}
    clearable
    groupLabelField="name"
    labelField="name"
    {options}
    {placeholder}
    valueField="id"
    bind:value
    {...props} />
