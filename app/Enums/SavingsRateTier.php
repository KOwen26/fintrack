<?php

namespace App\Enums;

/**
 * Savings-rate bands for a single month. The thresholds are business rules owned
 * by the backend so the frontend only colors/bands by the returned value — a
 * future per-user target preference can move the numbers without touching UI.
 *
 * Reasoning for the default 20% healthy line:
 *  - 20% is the "S" in the 50/30/20 rule and matches BLS-derived guidance for
 *    a sustainable personal savings rate that leaves room for emergencies and
 *    long-term goals.
 *  - 0–20% is "caution": the month is positive, but a single bad month wipes
 *    the buffer, so resilience is thin.
 *  - < 0% is "negative": spending exceeded income — the month drew down
 *    savings or took on debt.
 */
enum SavingsRateTier: string
{
    case Healthy = 'healthy';
    case Caution = 'caution';
    case Negative = 'negative';

    /** The rate (percent) at or above which a month counts as healthy. */
    public static function healthyThreshold(): float
    {
        return 20.0;
    }

    /** The lower bound (inclusive) of the caution band. */
    public static function cautionThreshold(): float
    {
        return 0.0;
    }

    public static function fromRate(float $rate): self
    {
        if ($rate >= self::healthyThreshold()) {
            return self::Healthy;
        }

        if ($rate >= self::cautionThreshold()) {
            return self::Caution;
        }

        return self::Negative;
    }

    /** @return array<string> Tier values counted toward the "on target" metric. */
    public static function onTargetTiers(): array
    {
        return [self::Healthy->value];
    }
}
