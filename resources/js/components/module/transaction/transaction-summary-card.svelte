<script lang="ts">
    import Formatter from '@utilities/formatter';
    import { cn } from '@utilities/shadcn';

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
        'relative isolate overflow-hidden rounded-xl p-4 text-primary-content shadow-xs lg:p-6',
        'bg-linear-135 from-primary to-primary-800',
        _class
    )}>
    <!-- Decorative glow blobs — clipped by the card, purely cosmetic -->
    <div class="pointer-events-none" aria-hidden="true">
        <div class="absolute -top-20 -right-15 size-50 rounded-full bg-white/25 blur-[42px]"></div>
        <div class="absolute -bottom-18 -left-12 size-40 rounded-full bg-accent/40 blur-[48px]">
        </div>
    </div>

    <div class="relative flex flex-col lg:flex-row lg:items-center">
        <!-- Hero: net cash flow -->
        <div class="flex items-start justify-between gap-3 lg:items-center">
            <div class="min-w-0">
                <span
                    class="block text-sm font-semibold tracking-widest text-primary-content/75 uppercase">
                    Net Cash Flow
                </span>
                <p
                    class="mt-2 flex items-center gap-1 font-mono text-3xl leading-none font-bold tracking-wide lg:mt-1.5">
                    {#if summary.net !== 0}
                        <i
                            class="iconify size-5 {summary.net > 0
                                ? 'solar--add-bold-duotone'
                                : 'solar--minus-bold-duotone'}"></i>
                    {/if}
                    {Formatter.currency(Math.abs(summary.net))}
                </p>
            </div>
            <div
                class="flex size-8 shrink-0 items-center justify-center rounded-md bg-white/20 lg:size-11 lg:rounded-xl">
                <i class="iconify size-5 solar--transfer-vertical-line-duotone lg:size-6"></i>
            </div>
        </div>

        <!-- Sub-stats: income & expense -->
        <div
            class="relative mt-4 flex justify-between gap-3 border-t border-primary-content/20 pt-3 lg:mt-0 lg:ml-8 lg:flex-1 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-primary-content/70">
                    <i class="iconify size-3 shrink-0 solar--arrow-down-line-duotone"></i>
                    <span
                        class="text-2xs font-semibold tracking-widest whitespace-nowrap uppercase">
                        Income
                    </span>
                </div>
                <p class="mt-1 font-mono text-base font-bold tracking-wide whitespace-nowrap">
                    {Formatter.currency(summary.income)}
                </p>
            </div>
            <div class="h-11 w-px min-w-0 border-l border-primary-content/20"></div>
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-primary-content/70">
                    <i class="iconify size-3 shrink-0 solar--arrow-up-line-duotone"></i>
                    <span
                        class="text-2xs font-semibold tracking-widest whitespace-nowrap uppercase">
                        Expense
                    </span>
                </div>
                <p class="mt-1 font-mono text-base font-bold tracking-wide whitespace-nowrap">
                    {Formatter.currency(summary.expense)}
                </p>
            </div>
        </div>
    </div>
</div>
