import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';

let breadcrumbItems = $state([]);

export const getBreadcrumbItems = () => breadcrumbItems;

export const setBreadcrumbItems = (items: Array<BreadcrumbItem>) => {
    breadcrumbItems = items;
};
