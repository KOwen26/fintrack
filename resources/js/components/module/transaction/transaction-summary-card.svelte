<script lang="ts">
    import { cn } from '@utilities/shadcn';

    import CurrencyAmount from '@components/data/currency-amount.svelte';

    interface Summary {
        income: number;
        expense: number;
        net: number;
    }

    interface Props {
        summary: Summary;
        class?: string;
    }

    let { summary, class: _class }: Props = $props();
</script>

<div
    class={cn(
        'relative isolate overflow-hidden rounded-xl p-4 text-primary-foreground shadow-xs lg:p-6',
        'bg-linear-135 from-primary to-primary-800',
        _class
    )}>
    <!-- Decorative glow blobs — clipped by the card, purely cosmetic -->
    <div class="pointer-events-none" aria-hidden="true">
        <div
            class="absolute -top-20 -right-15 size-52 rounded-full bg-white/25 blur-[42px] lg:-top-32 lg:-right-25 lg:size-80">
        </div>
        <div
            class="absolute -bottom-18 -left-12 size-42 rounded-full bg-accent/40 blur-[48px] lg:-bottom-28 lg:-left-20 lg:size-65">
        </div>
        <div
            class="absolute right-[34%] bottom-6 size-25 rounded-full bg-white/10 blur-[30px] lg:right-[36%] lg:bottom-11 lg:size-36">
        </div>
    </div>

    <div class="relative flex flex-col lg:flex-row lg:items-center">
        <!-- Hero: net cash flow -->
        <div class="flex items-start justify-between gap-3 lg:items-center">
            <div class="min-w-0">
                <h6 class="text-sm font-medium tracking-wider text-primary-foreground/70 uppercase">
                    Net Cash Flow
                </h6>
                <p
                    class="mt-2 flex items-center gap-1 text-3xl leading-none font-bold tracking-tight lg:mt-1.5">
                    <CurrencyAmount
                        class="text-3xl leading-none font-bold tracking-tight"
                        value={summary.net} />
                </p>
            </div>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 lg:order-first">
                <i class="iconify size-5 solar--transfer-vertical-line-duotone"></i>
            </div>
        </div>

        <!-- Sub-stats: income & expense -->
        <div
            class="mt-4 grid grid-cols-2 border-t border-primary-foreground/40 pt-3 lg:mt-0 lg:ml-8 lg:flex-1 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-primary-foreground/70">
                    <i class="iconify size-5 shrink-0 solar--arrow-down-line-duotone"></i>
                    <span class="text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                        Income
                    </span>
                </div>
                <CurrencyAmount
                    class="mt-1 text-base font-bold tracking-tight lg:mt-1.5"
                    value={summary.income} />
            </div>

            <div class="min-w-0 border-l border-primary-foreground/40 pl-4">
                <div class="flex items-center gap-1.5 text-primary-foreground/70">
                    <i class="iconify size-5 shrink-0 solar--arrow-up-line-duotone"></i>
                    <span class="text-xs font-semibold tracking-wider whitespace-nowrap uppercase">
                        Expense
                    </span>
                </div>
                <CurrencyAmount
                    class="mt-1 text-base font-bold tracking-tight lg:mt-1.5"
                    value={summary.expense} />
            </div>
        </div>
    </div>
</div>
