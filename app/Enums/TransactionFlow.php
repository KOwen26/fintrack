<?php

namespace App\Enums;

enum TransactionFlow: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';

    public function isInflow(): bool
    {
        return $this === self::Inflow;
    }

    public function isOutflow(): bool
    {
        return $this === self::Outflow;
    }
}
