<script lang="ts">
    import { setBreadcrumbItems } from '@lib/global-states.svelte';
    import { pageLayoutProps } from '@lib/navigation-helper.svelte';

    import {
        Card,
        CardAction,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@components/ui/atoms/card';
    import Button from '@components/ui/button.svelte';

    setBreadcrumbItems([{ title: 'Dev' }, { title: 'Design' }, { title: 'Cards' }]);
    pageLayoutProps({ title: 'Design — Card atoms' });

    const sizes = ['xs', 'sm', 'md', 'lg', 'xl'] as const;
</script>

<div class="space-y-8 p-3 md:p-0">
    <div>
        <h1>Design — Card atoms</h1>
        <p class="text-sm text-foreground/60">
            The <code>atoms/card</code> primitives composed by hand:
            <code>Card</code> is an unopinionated flex column with spacing, ring, and paint;
            <code>CardHeader</code>
            is a description-aware grid; the rest are typed slots. The prebuilt
            <code>ui/cards/*</code> components wrap these.
        </p>
    </div>

    <div class="grid gap-8 md:grid-cols-2 xl:grid-cols-3">
        <section>
            <h2 class="text-lg font-semibold">Content only</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                The minimum card — paint, spacing, and a content region.
            </p>
            <Card>
                <CardContent>
                    <p class="text-sm text-foreground/70">
                        A card without a header is just a framed surface.
                    </p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Header with title</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                <code>CardHeader</code> is a grid; <code>CardTitle</code> is a plain typed div.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Title + description</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                The header detects the description and switches to two rows.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardDescription>Income minus expense across the period.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Action, no description</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                Title + action center vertically — nothing else shares the header.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardAction>
                        <Button size="icon-sm" variant="outline">
                            <i class="iconify size-4 solar--refresh-bold-duotone"></i>
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Action with description</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                With a description the action spans both rows, pinned to the title's top.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardDescription>Income minus expense across the period.</CardDescription>
                    <CardAction>
                        <Button size="icon-sm" variant="outline">
                            <i class="iconify size-4 solar--refresh-bold-duotone"></i>
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Footer</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                <code>CardFooter</code> is a separated, muted strip; the root drops its bottom padding
                when one is present.
            </p>
            <Card>
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardDescription>Income minus expense across the period.</CardDescription>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
                <CardFooter class="justify-end gap-2">
                    <Button size="sm" variant="outline">Cancel</Button>
                    <Button size="sm">Save</Button>
                </CardFooter>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Sizes</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                <code>size</code> maps to the <code>--card-spacing</code> scale: xs=3, sm=4, md=5 (default),
                lg=6, xl=8.
            </p>
            <div class="grid gap-3 sm:grid-cols-2">
                {#each sizes as cardSize (cardSize)}
                    <Card size={cardSize}>
                        <CardHeader>
                            <CardTitle class="capitalize">{cardSize}</CardTitle>
                            <CardDescription
                                >Income minus expense across the period.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                        </CardContent>
                    </Card>
                {/each}
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Flat variant</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                <code>variant="flat"</code> drops every paint utility — background, ring, rounding, clipping
                — keeping layout, spacing, and the data-slot contract. Embeddable inside hero/sheet layouts.
            </p>
            <Card variant="flat">
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardAction>
                        <Button size="icon-sm" variant="ghost">
                            <i class="iconify size-4 solar--refresh-bold-duotone"></i>
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Flat - full variant</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                <code>variant="flat"</code> drops every paint utility — background, ring, rounding, clipping
                — keeping layout, spacing, and the data-slot contract. Embeddable inside hero/sheet layouts.
            </p>
            <Card variant="flat">
                <CardHeader>
                    <CardTitle>Monthly Cashflow</CardTitle>
                    <CardDescription>Income minus expense across the period.</CardDescription>
                    <CardAction>
                        <Button size="icon-sm" variant="ghost">
                            <i class="iconify size-4 solar--refresh-bold-duotone"></i>
                        </Button>
                    </CardAction>
                </CardHeader>
                <CardContent>
                    <p class="text-sm text-foreground/70">Net Rp1.240.000 for October.</p>
                </CardContent>
                <CardFooter class="justify-end gap-2">
                    <Button size="sm" variant="outline">Cancel</Button>
                    <Button size="sm">Save</Button>
                </CardFooter>
            </Card>
        </section>

        <section>
            <h2 class="text-lg font-semibold">Free-form children</h2>
            <p class="mb-3 text-xs text-muted-foreground">
                The root is just a gap'd flex column — children compose directly, no slots required.
            </p>
            <Card>
                <div class="flex items-center gap-3">
                    <div class="flex size-10 items-center justify-center rounded-lg bg-primary/10">
                        <i class="iconify size-5 text-primary solar--wallet-bold-duotone"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold">Wallet</p>
                        <p class="text-xs text-muted-foreground">Primary account</p>
                    </div>
                </div>
                <CardContent class="px-0">
                    <p class="text-sm text-foreground/70">
                        Any node works — <code>CardContent</code> only contributes the side padding.
                    </p>
                </CardContent>
            </Card>
        </section>
    </div>
</div>
