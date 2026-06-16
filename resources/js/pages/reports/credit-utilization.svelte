<script lang="ts">
    import type { App } from '@wayfinder/types';

    import ReportsController from '@wayfinder/App/Http/Controllers/ReportsController';

    import CreditUtilizationGauge from '@components/module/report/credit-utilization-gauge.svelte';
    import Button from '@components/ui/button.svelte';
    import Card from '@components/ui/card.svelte';

    interface CreditUtilization {
        limit: number;
        used: number;
        available: number;
        utilization_pct: number;
        alert_level: App.Enums.AlertLevel;
    }

    let {
        account,
        credit_utilization,
    }: {
        account: App.Models.Account;
        credit_utilization: CreditUtilization;
    } = $props();
</script>

<div class="p-4">
    <div class="mb-4 flex items-center gap-3">
        <Button
            class="btn-circle btn-sm"
            color="light"
            href={ReportsController.index.url({ account: account.id })}
            variant="ghost">
            <i class="iconify size-5 ph--arrow-left-bold"></i>
        </Button>
        <div>
            <h1 class="text-xl font-bold">Credit Utilization</h1>
            <p class="text-sm text-base-content/50">{account.name}</p>
        </div>
    </div>

    <Card wrapperClass="mb-4">
        <CreditUtilizationGauge
            alert_level={credit_utilization.alert_level}
            available={credit_utilization.available}
            limit={credit_utilization.limit}
            used={credit_utilization.used}
            utilization_pct={credit_utilization.utilization_pct} />
    </Card>

    <!-- Guidance text per alert level -->
    <Card>
        <div class="text-sm text-base-content/70">
            {#if credit_utilization.utilization_pct >= 70}
                <p class="font-semibold text-error">High risk — above 70%</p>
                <p class="mt-1">
                    Your utilization is very high. This may affect your credit score. Pay down your
                    balance as soon as possible.
                </p>
            {:else if credit_utilization.utilization_pct >= 30}
                <p class="font-semibold text-warning">Caution — 30% to 69%</p>
                <p class="mt-1">
                    Your utilization is elevated. Aim to keep it below 30% to maintain a healthy
                    credit profile.
                </p>
            {:else}
                <p class="font-semibold text-success">Healthy — below 30%</p>
                <p class="mt-1">
                    Your utilization is within a healthy range. Keep it here to protect your credit
                    score.
                </p>
            {/if}
        </div>
    </Card>
</div>
