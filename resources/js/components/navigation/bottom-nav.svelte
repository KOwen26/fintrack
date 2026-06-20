<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import CategoryController from '@wayfinder/App/Http/Controllers/CategoryController';
    import TransactionController from '@wayfinder/App/Http/Controllers/TransactionController';

    import { cn } from '@utilities/shadcn';

    const currentRoute = $derived(
        (page.props.meta as { current_route_name?: string } | null)?.current_route_name ?? ''
    );

    const isActive = (prefix: string) => currentRoute.startsWith(prefix);
</script>

<nav
    class="btm-nav btm-nav-sm fixed bottom-0 left-0 right-0 z-50 border-t border-base-300 bg-base-100">
    <Link
        class={isActive('accounts') ? 'active' : ''}
        aria-label="Accounts"
        href={AccountController.index.url()}>
        <i class="iconify size-5 ph--wallet-bold"></i>
        <span class="btm-nav-label text-xs">Accounts</span>
    </Link>

    <a
        class:active={isActive('categories')}
        aria-label="Categories"
        href={CategoryController.index.url()}>
        <i class="iconify size-5 ph--tag-bold"></i>
        <span class="btm-nav-label text-xs">Categories</span>
    </a>

    <Link
        class={cn('rounded-full size-12 bg-primary', isActive('accounts') ? 'active' : '')}
        aria-label="Add"
        href={TransactionController.create.url()}>
        <i class="iconify size-6 ph--plus-bold"></i>
    </Link>

    <a class:active={isActive('dashboard')} aria-label="Reports" href="/dashboard">
        <i class="iconify size-5 ph--chart-bar-bold"></i>
        <span class="btm-nav-label text-xs">Reports</span>
    </a>
</nav>
