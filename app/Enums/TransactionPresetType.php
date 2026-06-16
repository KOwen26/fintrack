<?php

namespace App\Enums;

enum TransactionPresetType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
}
