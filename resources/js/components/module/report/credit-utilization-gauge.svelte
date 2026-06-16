<script lang="ts">
    import type { App } from '@wayfinder/types';

    import CreditAlertBadge from './credit-alert-badge.svelte';

    import AlertLevel from '@wayfinder/App/Enums/AlertLevel';

    let {
        limit,
        used,
        available,
        utilization_pct,
        alert_level,
    }: {
        limit: number;
        used: number;
        available: number;
        utilization_pct: number;
        alert_level: App.Enums.AlertLevel;
    } = $props();

    // DaisyUI semantic color tokens mapped by alert level
    const gaugeColorClass = $derived(
        {
            [AlertLevel.Normal]: 'text-success',
            [AlertLevel.Warning]: 'text-warning',
            [AlertLevel.HighRisk]: 'text-error',
        }[alert_level] ?? 'text-success'
    );

    // Radial progress uses a CSS custom property --value (0–100)
    const pct = $derived(Math.min(100, Math.round(utilization_pct)));

    function formatIDR(value: number): string {
        return value.toLocaleString('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        });
    }
</script>

<div class="flex flex-col items-center gap-4">
    <!-- DaisyUI radial-progress gauge -->
    <div
        style="--value:{pct}; --size:10rem; --thickness:1rem;"
        class="radial-progress {gaugeColorClass} text-xl font-bold"
        aria-valuemax={100}
        aria-valuemin={0}
        aria-valuenow={pct}
        role="progressbar">
        {pct}%
    </div>

    <CreditAlertBadge level={alert_level} />

    <!-- Stats grid -->
    <div class="grid w-full grid-cols-3 gap-2 text-center text-sm">
        <div>
            <p class="text-xs text-base-content/50">Limit</p>
            <p class="font-mono font-semibold">{formatIDR(limit)}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">Used</p>
            <p class="font-mono font-semibold text-error">{formatIDR(used)}</p>
        </div>
        <div>
            <p class="text-xs text-base-content/50">Available</p>
            <p class="font-mono font-semibold text-success">{formatIDR(available)}</p>
        </div>
    </div>
</div>
