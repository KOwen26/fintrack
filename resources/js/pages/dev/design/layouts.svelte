<script lang="ts">
    import { setBreadcrumbItems } from '@lib/global-states.svelte';

    import MobilePageLayout from '@components/layouts/mobile-page-layout.svelte';

    setBreadcrumbItems([{ title: 'Dev' }, { title: 'Design System' }, { title: 'Layouts' }]);

    const variants = [
        { name: 'full', description: 'No hero — the sheet fills the entire screen.' },
        { name: '1/5', description: 'Sheet 20% / hero 80%.' },
        { name: '2/5', description: 'Sheet 40% / hero 60% — the example dashboard proportions.' },
        { name: '1/2', description: 'Even split.' },
        { name: '3/5', description: 'Sheet 60% / hero 40%.' },
        { name: '4/5', description: 'Sheet 80% / hero 20% — list-dominant screens.' },
    ] as const;

    const skeletons = [1, 2, 3, 4];
</script>

<div class="space-y-8 p-3 md:p-0">
    <div>
        <h1>Design System — Mobile Page Layout</h1>
        <p class="text-sm text-base-content/60">
            Every sheet variant of mobile-page-layout, framed at phone size for desktop preview. The
            split only sizes the regions — scrolling stays the page's decision.
        </p>
    </div>

    <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
        {#each variants as variant (variant.name)}
            <section>
                <h2 class="text-lg font-semibold">{variant.name}</h2>
                <p class="mb-3 text-xs text-base-content/50">{variant.description}</p>

                <div
                    class="mx-auto w-full max-w-80 overflow-hidden rounded-3xl border border-base-300 shadow-lg">
                    <MobilePageLayout class="h-[560px]" variant={variant.name}>
                        {#snippet hero()}
                            <div class="flex h-full flex-col gap-4 px-6 pt-6">
                                <div>
                                    <p class="text-sm font-medium text-secondary-content/60">
                                        Total balance
                                    </p>
                                    <p class="text-4xl font-bold tabular-nums">Rp367.289</p>
                                </div>

                                <div class="flex gap-2">
                                    <div
                                        class="flex-1 rounded-full bg-secondary-content/10 py-3 text-center text-sm font-semibold">
                                        Transfer
                                    </div>
                                    <div
                                        class="flex-1 rounded-full bg-secondary-content/10 py-3 text-center text-sm font-semibold">
                                        Request
                                    </div>
                                </div>

                                <div class="h-24 rounded-3xl bg-primary"></div>
                            </div>
                        {/snippet}

                        <div class="space-y-3 pt-1">
                            <h3 class="text-base font-bold">Latest Transactions</h3>
                            {#each skeletons as skeleton (skeleton)}
                                <div class="flex items-center gap-3">
                                    <div class="size-10 shrink-0 rounded-full bg-base-300"></div>
                                    <div class="min-w-0 flex-1 space-y-1.5">
                                        <div class="h-3 w-3/4 rounded bg-base-300"></div>
                                        <div class="h-2 w-1/2 rounded bg-base-200"></div>
                                    </div>
                                    <div class="h-3 w-12 rounded bg-base-200"></div>
                                </div>
                            {/each}
                        </div>
                    </MobilePageLayout>
                </div>
            </section>
        {/each}
    </div>
</div>
