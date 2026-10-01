<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum AccountType: string
{
    case DebitAccount = 'debit_account';
    case CreditCard = 'credit_card';
    case CashWallet = 'cash_wallet';
    case EWallet = 'e_wallet';
    case Investment = 'investment';

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
