<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { page } from '@inertiajs/svelte';
    import Svelecte from 'svelecte';

    import { cn } from '@utilities/shadcn';

    interface Props {
        value?: any;
        endpoint?: string;
        accounts?: App.Models.Account[];
        placeholder?: string;
        class?: string;
    }

    let {
        value = $bindable(),
        accounts = [],
        placeholder = 'Select account',
        class: _class,
        ...props
    }: Props = $props();

    const options = $derived(accounts.length ? accounts : page.props?.static?.accounts);
</script>

<Svelecte
    class={cn('w-full', _class)}
    labelField="name"
    {options}
    {placeholder}
    valueField="id"
    bind:value
    {...props} />
