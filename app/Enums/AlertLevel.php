<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Normal = 'normal';
    case Warning = 'warning';
    case HighRisk = 'high_risk';
}
