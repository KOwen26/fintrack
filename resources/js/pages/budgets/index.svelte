<script lang="ts">
    import type { BudgetStatusData } from '@/types/generated';
    import type { App } from '@wayfinder/types';

    import { router } from '@inertiajs/svelte';
    import AccountController from '@wayfinder/App/Http/Controllers/AccountController';
    import BudgetController from '@wayfinder/App/Http/Controllers/BudgetController';

    import BudgetForm from '@components/module/budget/budget-form.svelte';
    import BudgetStatusBadge from '@components/module/budget/budget-status-badge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';
    import ConfirmationModal from '@components/ui/modals/confirmation-modal.svelte';

    interface BudgetWithStatus {
        budget: App.Models.Budget;
        status: BudgetStatusData;
    }

    let {
        account,
        budgets_with_status,
        year,
        month,
        categories,
    }: {
        account: App.Models.Account;
        budgets_with_status: BudgetWithStatus[];
        year: number;
        month: number;
        categories: App.Models.Category[];
    } = $props();

    let showAddForm = $state(false);
    let editingBudgetId = $state<number | null>(null);
    let deletingBudgetId = $state<number | null>(null);

    const monthNames = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    const currentMonthLabel = $derived(`${monthNames[month - 1]} ${year}`);

    function navigateMonth(delta: number): void {
        let newMonth = month + delta;
        let newYear = year;

        if (newMonth > 12) {
            newMonth = 1;
            newYear++;
        } else if (newMonth < 1) {
            newMonth = 12;
            newYear--;
        }

        router.visit(
            BudgetController.index.url({
                account: account.id,
                query: { year: newYear, month: newMonth },
            }),
            { preserveState: false }
        );
    }

    function destroyBudget(): void {
        if (!deletingBudgetId) {
            return;
        }

        router.delete(
            BudgetController.destroy.url({ account: account.id, budget: deletingBudgetId }),
            { onFinish: () => (deletingBudgetId = null) }
        );
    }

    function progressColor(status: string): string {
        if (status === 'over_budget') {
            return 'progress-error';
        }
        if (status === 'at_risk') {
            return 'progress-warning';
        }

        return 'progress-success';
    }
</script>

<div class="p-4">
    <!-- Header -->
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={AccountController.show.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div class="flex-1">
            <h1 class="text-xl font-bold">{account.name}</h1>
            <p class="text-xs text-base-content/50">Budgets</p>
        </div>
        <Button color="primary" onclick={() => (showAddForm = !showAddForm)} size="sm">
            <i class="iconify size-4 ph--plus-bold"></i>
            Add
        </Button>
    </div>

    <!-- Month navigation -->
    <div class="mb-4 flex items-center justify-between rounded-xl bg-base-200 px-4 py-2">
        <Button
            class="btn-circle btn-sm"
            color="light"
            onclick={() => navigateMonth(-1)}
            variant="ghost">
            <i class="iconify size-5 ph--caret-left-bold"></i>
        </Button>
        <p class="font-semibold">{currentMonthLabel}</p>
        <Button
            class="btn-circle btn-sm"
            color="light"
            onclick={() => navigateMonth(1)}
            variant="ghost">
            <i class="iconify size-5 ph--caret-right-bold"></i>
        </Button>
    </div>

    <!-- Add budget form -->
    {#if showAddForm}
        <BudgetForm
            {account}
            {categories}
            defaultMonth={month}
            defaultYear={year}
            onCancel={() => (showAddForm = false)}
            onSuccess={() => (showAddForm = false)} />
    {/if}

    <!-- Budget list -->
    {#if budgets_with_status.length === 0}
        <div class="flex flex-col items-center justify-center py-16 text-base-content/40">
            <i class="iconify mb-3 size-12 ph--piggy-bank-bold"></i>
            <p class="text-sm">No budgets for this month</p>
            <Button class="mt-4" color="primary" onclick={() => (showAddForm = true)} size="sm">
                Set your first budget
            </Button>
        </div>
    {:else}
        <div class="space-y-3">
            {#each budgets_with_status as { budget, status } (budget.id)}
                {#if editingBudgetId === budget.id}
                    <BudgetForm
                        {account}
                        {budget}
                        {categories}
                        defaultMonth={month}
                        defaultYear={year}
                        onCancel={() => (editingBudgetId = null)}
                        onSuccess={() => (editingBudgetId = null)} />
                {:else}
                    <Card>
                        <!-- Top row: category name + status badge + actions -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold">
                                        {budget.category?.name ?? 'Category'}
                                    </p>
                                    <BudgetStatusBadge status={status.status} />
                                </div>

                                <!-- Progress bar -->
                                <div class="mt-2">
                                    <progress
                                        class="progress h-2 w-full {progressColor(status.status)}"
                                        max="100"
                                        value={Math.min(status.percentage, 100)}></progress>
                                </div>

                                <!-- Spend / limit figures -->
                                <div class="mt-1 flex items-center justify-between">
                                    <p class="text-xs text-base-content/60">
                                        Spent: <span class="font-mono font-medium">
                                            {Number(status.spend).toLocaleString('id-ID', {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })}
                                        </span>
                                    </p>
                                    <p class="text-xs text-base-content/60">
                                        Limit: <span class="font-mono font-medium">
                                            {Number(status.limit_amount).toLocaleString('id-ID', {
                                                minimumFractionDigits: 0,
                                                maximumFractionDigits: 0,
                                            })}
                                        </span>
                                    </p>
                                    <p
                                        class="text-xs font-semibold {status.percentage >= 100
                                            ? 'text-error'
                                            : 'text-base-content/60'}">
                                        {status.percentage.toFixed(0)}%
                                    </p>
                                </div>
                            </div>

                            <!-- Edit / delete actions -->
                            <div class="flex shrink-0 flex-col gap-1">
                                <Button
                                    class="btn-xs"
                                    color="light"
                                    onclick={() => (editingBudgetId = budget.id)}
                                    variant="ghost">
                                    <i class="iconify size-4 ph--pencil-simple-bold"></i>
                                </Button>
                                <Button
                                    class="btn-xs"
                                    color="error"
                                    onclick={() => (deletingBudgetId = budget.id)}
                                    variant="ghost">
                                    <i class="iconify size-4 ph--trash-bold"></i>
                                </Button>
                            </div>
                        </div>
                    </Card>
                {/if}
            {/each}
        </div>
    {/if}
</div>

<ConfirmationModal
    cancelText="Cancel"
    confirmButtonProps={{ color: 'error' }}
    confirmText="Delete"
    onCancel={() => (deletingBudgetId = null)}
    onConfirm={destroyBudget}
    title="Delete Budget"
    bind:open={deletingBudgetId}>
    This budget will be soft-deleted. Existing transactions are unaffected.
</ConfirmationModal>
