<script lang="ts">
    import { cn } from '@utilities/shadcn';

    import Button from '@components/ui/button.svelte';

    interface Props {
        label: string;
        icon?: string;
        ctaUrl?: string;
        ctaLabel?: string;
        ctaOnclick?: () => void;
    }

    let { label, icon, ctaUrl, ctaLabel, ctaOnclick }: Props = $props();

    const hasIcon = $derived(!!icon?.length);
    const hasCta = $derived(!!ctaUrl?.length || !!ctaOnclick);
</script>

<div class="flex flex-col items-center justify-center gap-6 py-16 text-base-content/80">
    <div class={cn(hasIcon ? 'block' : 'hidden')}>
        <i class={cn('iconify size-12', hasIcon ? icon : '')}></i>
    </div>

    <p class="text-pretty">{label}</p>

    {#if hasCta}
        <Button color="primary" href={ctaUrl} onclick={ctaOnclick}>
            {ctaLabel ?? label}
        </Button>
    {/if}
</div>
