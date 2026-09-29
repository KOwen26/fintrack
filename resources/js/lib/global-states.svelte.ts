import type { BreadcrumbItem } from '@components/ui/breadcrumbs.svelte';

// ── Sidebar ──

export const sidebar = $state({
    is_collapsed: localStorage.getItem('sidebar-collapse') === 'true' || false,
    collapse() {
        this.is_collapsed = !this.is_collapsed;
        localStorage.setItem('sidebar-collapse', this.is_collapsed.toString());
    },
});

// ── Breadcrumbs ──

let breadcrumbItems = $state<BreadcrumbItem[]>([]);

export const getBreadcrumbItems = () => breadcrumbItems;

export const setBreadcrumbItems = (items: Array<BreadcrumbItem>) => {
    breadcrumbItems = items;
};
