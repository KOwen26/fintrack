<script lang="ts">
    import { APP_NAME } from '@data/constants';
    import { useTheme } from '@lib/theme-handler.svelte';

    import BottomNav from '@components/navigation/bottom-nav.svelte';
    import Toaster from '@components/ui/toaster.svelte';

    let { children } = $props();

    useTheme();
</script>

<svelte:head>
    <title>{import.meta.env.VITE_APP_NAME || APP_NAME}</title>
</svelte:head>

<Toaster />
<div class="min-h-screen bg-base-100 pb-20">
    {@render children()}
</div>
<BottomNav />
