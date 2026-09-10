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
        <div class="absolute -top-20 -right-15 size-[210px] rounded-full bg-white/25 blur-[42px]">
        </div>
        <div
            class="absolute -bottom-[70px] -left-[50px] size-[170px] rounded-full bg-accent/40 blur-[48px]">
        </div>
    </div>

    <div class="relative flex flex-col lg:flex-row lg:items-center">
        <!-- Hero: net cash flow -->
        <div class="flex items-start justify-between gap-3 lg:items-center">
            <div class="min-w-0">
                <span
                    class="block text-[0.68rem] font-semibold tracking-[0.09em] text-primary-content/75 uppercase">
                    Net Cash Flow
                </span>
                <p
                    class="mt-2 flex items-center gap-1 font-mono text-[1.75rem] leading-none font-bold tracking-tight lg:mt-1.5">
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
                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/20 lg:size-11 lg:rounded-[0.625rem]">
                <i class="iconify size-5 solar--transfer-vertical-line-duotone lg:size-6"></i>
            </div>
        </div>

        <!-- Sub-stats: income & expense -->
        <div
            class="relative mt-4 grid grid-cols-2 border-t border-primary-content/20 pt-3 lg:mt-0 lg:ml-8 lg:flex-1 lg:border-t-0 lg:border-l lg:pt-0 lg:pl-8">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5 text-primary-content/70">
                    <i class="iconify size-3.5 shrink-0 solar--arrow-up-line-duotone"></i>
                    <span
                        class="text-[0.6rem] font-semibold tracking-[0.08em] whitespace-nowrap uppercase">
                        Income
                    </span>
                </div>
                <p class="mt-1 font-mono text-[0.95rem] font-bold tracking-tight whitespace-nowrap">
                    {Formatter.currency(summary.income)}
                </p>
            </div>
            <div class="ml-3.5 min-w-0 border-l border-primary-content/20 pl-3.5">
                <div class="flex items-center gap-1.5 text-primary-content/70">
                    <i class="iconify size-3.5 shrink-0 solar--arrow-down-line-duotone"></i>
                    <span
                        class="text-[0.6rem] font-semibold tracking-[0.08em] whitespace-nowrap uppercase">
                        Expense
                    </span>
                </div>
                <p class="mt-1 font-mono text-[0.95rem] font-bold tracking-tight whitespace-nowrap">
                    {Formatter.currency(summary.expense)}
                </p>
            </div>
        </div>
    </div>
</div>
