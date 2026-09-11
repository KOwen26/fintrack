<?php

namespace App\Enums;

enum TransactionFlow: string
{
    case Inflow = 'inflow';
    case Outflow = 'outflow';
}
