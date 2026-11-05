<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    public function toCashflow(): Cashflow
    {
        return match ($this) {
            self::Income => Cashflow::Inflow,
            self::Expense, self::Transfer => Cashflow::Outflow,
        };
    }
}
