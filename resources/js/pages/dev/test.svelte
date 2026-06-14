<script lang="ts">
    import Accordion from '@components/ui/accordion.svelte';
    import Breadcrumbs from '@components/ui/breadcrumbs.svelte';
</script>

<div>
    <Breadcrumbs
        items={[
            { title: 'Home', href: '/' },
            { title: 'Category', href: '/category' },
            { title: 'Subcategory', href: '/category/subcategory' },
            { title: 'Item', href: '/category/subcategory/item' },
            { title: 'Subcategory', href: '/category/subcategory' },
            { title: 'Item', href: '/category/subcategory/item' },
        ]} />

    <Accordion
        items={[
            {
                value: 'item-1',
                trigger: 'Item 1',
                content: 'Content for Item 1',
            },
            {
                value: 'item-2',
                trigger: 'Item 2',
                content: 'Content for Item 2',
            },
            {
                value: 'item-3',
                trigger: 'Item 3',
                content: 'Content for Item 3',
            },
        ]} />
</div>
