<script lang="ts">
    import { settingsMenu } from '@data/menu';

    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    const settingsItems = Object.values(settingsMenu.menus);
</script>

<Card title="Settings">
    <div class="grid gap-3">
        {#each settingsItems as item (item.route ?? item.name)}
            <Button
                class="h-auto min-h-16 w-full justify-between rounded-xl px-4 py-3 text-left"
                aria-label={item.name}
                color="secondary"
                href={item.url}
                useRouter={{ viewTransition: true }}
                variant="outline">
                <span class="flex min-w-0 items-center gap-3">
                    <span class="grid size-10 shrink-0 place-items-center rounded-xl bg-base-200">
                        {#if typeof item.icon === 'string'}
                            <i class="iconify size-5 {item.icon}" aria-hidden="true"></i>
                        {/if}
                    </span>

                    <span class="min-w-0 text-sm font-semibold">{item.name}</span>
                </span>

                <i
                    class="iconify size-4 shrink-0 text-base-content/40 solar--alt-arrow-right-linear"
                    aria-hidden="true"></i>
            </Button>
        {/each}
    </div>
</Card>
