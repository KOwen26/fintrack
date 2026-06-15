<?php

namespace App\Enums;

enum ProviderType: string
{
    case Bank = 'bank';
    case DigitalBank = 'digital_bank';
    case EWallet = 'e_wallet';
    case CreditLoan = 'credit_loan';
    case Investment = 'investment';
}
