<?php

namespace App\Enums;

enum AccountType: string
{
    case DebitAccount = 'debit_account';
    case CreditCard = 'credit_card';
    case CashWallet = 'cash_wallet';
    case EWallet = 'e_wallet';
    case Investment = 'investment';
}
