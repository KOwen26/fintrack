<script lang="ts">
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import ReportController from '@wayfinder/App/Http/Controllers/ReportController';
    import { SvelteDate } from 'svelte/reactivity';

    import ContributionGauge from '@components/module/report/contribution-gauge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface Member {
        name: string;
        contributed: number;
        percentage: number;
    }
    interface ContributionSplit {
        is_joint: boolean;
        members: Member[];
        total: number;
        from: string;
        to: string;
    }

    let {
        account,
        contribution_split,
        from,
        to,
    }: {
        account: App.Models.Account;
        contribution_split: ContributionSplit;
        from: string;
        to: string;
    } = $props();

    function navigatePeriod(direction: 'prev' | 'next') {
        const current = new SvelteDate(from);
        current.setMonth(current.getMonth() + (direction === 'prev' ? -1 : 1));
        const newFrom = new Date(current.getFullYear(), current.getMonth(), 1);
        const newTo = new Date(current.getFullYear(), current.getMonth() + 1, 0);

        router.visit(
            ReportController.contributionSplit.url({
                account: account.id,
                query: {
                    from: newFrom.toISOString().slice(0, 10),
                    to: newTo.toISOString().slice(0, 10),
                },
            }),
            { preserveScroll: true }
        );
    }

    const periodLabel = $derived(
        new Date(from).toLocaleString('default', { month: 'long', year: 'numeric' })
    );
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={ReportController.index.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Contribution Split</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    {#if !contribution_split.is_joint}
        <!-- Personal account empty state -->
        <div
            class="flex flex-col items-center justify-center py-16 text-center text-base-content/50">
            <i class="iconify mb-3 size-12 ph--users-slash-bold"></i>
            <p class="font-semibold">Joint accounts only</p>
            <p class="mt-1 max-w-xs text-sm">
                Contribution split is only available for joint accounts. This account is personal.
            </p>
        </div>
    {:else}
        <!-- Period nav -->
        <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-3 py-2">
            <Button
                class="btn-xs btn-circle"
                color="light"
                onclick={() => navigatePeriod('prev')}
                variant="ghost">
                <i class="iconify size-4 ph--caret-left-bold"></i>
            </Button>
            <span class="text-sm font-medium">{periodLabel}</span>
            <Button
                class="btn-xs btn-circle"
                color="light"
                onclick={() => navigatePeriod('next')}
                variant="ghost">
                <i class="iconify size-4 ph--caret-right-bold"></i>
            </Button>
        </div>

        <Card class="mb-4" title="Income by Member">
            <ContributionGauge
                members={contribution_split.members}
                total={contribution_split.total} />
        </Card>
    {/if}
</div>
