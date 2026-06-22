<script lang="ts">
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import Card from '@components/ui/card.svelte';

    interface CategoryItem {
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }

    interface CategorySpendingReport {
        categories: CategoryItem[];
        period_total: number;
        from: string;
        to: string;
    }

    let {
        category_spending = null,
    }: {
        category_spending?: CategorySpendingReport | null;
    } = $props();
</script>

{#if category_spending}
    <Card class="p-6">
        <h2 class="mb-4 text-lg font-semibold">Spending by Category</h2>
        <CategorySpendingChart
            categories={category_spending.categories}
            periodTotal={category_spending.period_total}
            periodLabel="This month" />
    </Card>
{/if}
