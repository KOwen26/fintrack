<script lang="ts">
    import { useFlashToast } from '@hooks/flash-handler.svelte';
    import { useTheme } from '@hooks/use-theme.svelte';

    import BottomNav from '@components/navigation/bottom-nav.svelte';
    import Toaster from '@components/ui/toaster.svelte';

    let { children } = $props();

    useTheme();
    useFlashToast();
</script>

<svelte:head>
    <title>{import.meta.env.VITE_APP_NAME || 'FinTrack'}</title>
</svelte:head>

<Toaster />
<div class="min-h-screen bg-base-100 pb-20">
    {@render children()}
</div>
<BottomNav />
