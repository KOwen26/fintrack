<?php

namespace App\Enums;

/**
 * Visual emphasis tier for chart marks — the heaviest data points of a
 * report's scope render at full strength, the rest step down the tint
 * ladder. Derived server-side alongside report peaks so the frontend
 * coloring can never disagree with the report's own ordering. Shared by
 * every bar-based statistic, not tied to any one report's domain.
 */
enum StatisticDataTier: string
{
    case Top = 'top';

    case Second = 'second';

    case Rest = 'rest';
}
