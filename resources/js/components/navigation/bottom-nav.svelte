<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AccountsController from '@wayfinder/App/Http/Controllers/AccountsController';
    import CategoriesController from '@wayfinder/App/Http/Controllers/CategoriesController';

    const currentRoute = $derived(
        (page.props.meta as { current_route_name?: string } | null)?.current_route_name ?? ''
    );
    const isActive = (prefix: string) => currentRoute.startsWith(prefix);
</script>

<nav
    class="btm-nav btm-nav-sm fixed bottom-0 left-0 right-0 z-50 border-t border-base-300 bg-base-100">
    <a
        class:active={isActive('accounts')}
        aria-label="Accounts"
        href={AccountsController.index.url()}>
        <i class="iconify size-5 ph--wallet-bold"></i>
        <span class="btm-nav-label text-xs">Accounts</span>
    </a>

    <a
        class:active={isActive('categories')}
        aria-label="Categories"
        href={CategoriesController.index.url()}>
        <i class="iconify size-5 ph--tag-bold"></i>
        <span class="btm-nav-label text-xs">Categories</span>
    </a>

    <button class="rounded-full bg-primary text-primary-content" aria-label="Quick add" disabled>
        <i class="iconify size-6 ph--plus-bold"></i>
    </button>

    <a class:active={isActive('dashboard')} aria-label="Reports" href="/dashboard">
        <i class="iconify size-5 ph--chart-bar-bold"></i>
        <span class="btm-nav-label text-xs">Reports</span>
    </a>
</nav>
