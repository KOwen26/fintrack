<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';

    /**
     * Types that increase the account balance (inflows).
     *
     * @return array<string>
     */
    public static function inflows(): array
    {
        return [self::Income->value, self::TransferIn->value];
    }

    /**
     * Types that decrease the account balance (outflows).
     *
     * @return array<string>
     */
    public static function outflows(): array
    {
        return [self::Expense->value, self::TransferOut->value];
    }
}
