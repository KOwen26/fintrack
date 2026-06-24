<script lang="ts">
    import CategorySpendingChart from '@components/module/report/category-spending-chart.svelte';
    import Card from '@components/ui/card.svelte';

    interface ChildItem {
        category_id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
    }

    interface ParentGroup {
        category_id: number;
        name: string;
        color: string;
        icon: string;
        total: number;
        percentage: number;
        children: ChildItem[];
    }

    interface CategorySpendingReport {
        categories: ParentGroup[];
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

<Card class="p-6">
    <h2 class="mb-4 text-lg font-semibold">Spending by Category</h2>
    <CategorySpendingChart
        categories={category_spending.categories}
        periodLabel="This month"
        periodTotal={category_spending.period_total} />
</Card>
