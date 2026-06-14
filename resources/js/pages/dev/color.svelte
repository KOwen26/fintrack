<script lang="ts">
    import { colorThemes, colorVariants } from '@data/theme';

    import Badge, { badgeVariants } from '@components/ui/badge.svelte';
    import Button, { buttonVariants } from '@components/ui/button.svelte';
</script>

<section>
    <h2>Colors</h2>
    <hr class="mt-2 mb-6" />
    <div class="grid grid-cols-8 gap-6">
        {#each Object.entries(colorThemes) as [colorVariant, colors] (colorVariant)}
            <div class="space-y-1">
                {#each Object.entries(colors) as [key, color] (color)}
                    <p class={['p-2 text-center text-sm font-medium capitalize', color]}>
                        {colorVariant} - {key}
                    </p>
                {/each}
            </div>
        {/each}
    </div>
    <div class="my-6">
        <div class="border-base-content/10 overflow-hidden rounded-lg border-[0.5px]">
            <div class="grid grid-cols-1">
                <div
                    class="bg-primary text-primary-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Primary
                    </div>
                </div>
                <div
                    class="bg-secondary text-secondary-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Secondary
                    </div>
                </div>
                <div
                    class="bg-accent text-accent-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Accent
                    </div>
                </div>
                <div
                    class="bg-neutral text-neutral-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Neutral
                    </div>
                </div>
            </div>
        </div>
        <div class="border-base-content/10 mt-4 overflow-hidden rounded-lg border-[0.5px]">
            <div class="grid xl:grid-cols-3">
                <div
                    class="bg-base-100 text-base-content group border-base-content/10 grid h-36 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Base 100
                    </div>
                </div>
                <div
                    class="bg-base-200 text-base-content group border-base-content/10 grid h-36 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Base 200
                    </div>
                </div>
                <div
                    class="bg-base-300 text-base-content group border-base-content/10 grid h-36 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Base 300
                    </div>
                </div>
            </div>
        </div>
        <div class="border-base-content/10 mt-4 overflow-hidden rounded-lg border-[0.5px]">
            <div class="grid xl:grid-cols-4">
                <div
                    class="bg-success text-success-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Success
                    </div>
                </div>
                <div
                    class="bg-info text-info-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Info
                    </div>
                </div>
                <div
                    class="bg-warning text-warning-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Warning
                    </div>
                </div>
                <div
                    class="bg-error text-error-content group border-base-content/10 grid h-24 place-content-end gap-1 p-6 text-end">
                    <div
                        class="font-title translate-y-1 text-sm font-semibold tracking-widest opacity-0 transition-all group-hover:translate-y-0 group-hover:opacity-100">
                        Error
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<section>
    <h2 id="buttons">Buttons</h2>
    <hr class="mt-2 mb-6" />
    <div class="grid grid-cols-2 gap-5">
        {#each buttonVariants as variant (variant)}
            <div>
                <h2 class="mb-2 font-medium">{variant.toUpperCase()}</h2>
                <div class="grid grid-cols-3 gap-3">
                    {#each colorVariants as color (color)}
                        <Button {color} {variant}>{`${color} Button`.toUpperCase()}</Button>
                    {/each}
                </div>
            </div>
        {/each}
    </div>
</section>
<section>
    <h2>Badge</h2>
    <hr class="mt-2 mb-6" />
    <div class="grid grid-cols-2 gap-5">
        {#each badgeVariants as variant (variant)}
            {@const isOutline = ['outline', 'outline-dash'].includes(variant)}
            {@const isSoft = variant === 'soft'}
            <div>
                <h2 class="mb-2 font-medium">
                    {variant.toUpperCase()} - {(isOutline
                        ? 'Square'
                        : isSoft
                          ? 'Pill'
                          : 'Rounded'
                    ).toUpperCase()}
                </h2>
                <div class="grid grid-cols-3 gap-3">
                    {#each colorVariants as color (color)}
                        <Badge
                            {color}
                            shape={isOutline ? 'square' : isSoft ? 'pill' : 'rounded'}
                            {variant}>{color} badge</Badge>
                    {/each}
                </div>
            </div>
        {/each}
    </div>
</section>
