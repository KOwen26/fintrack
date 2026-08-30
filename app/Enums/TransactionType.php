<?php

namespace App\Enums;

enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case TransferOut = 'transfer_out';
    case TransferIn = 'transfer_in';
    case Fee = 'fee';

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
        return [self::Expense->value, self::TransferOut->value, self::Fee->value];
    }

    /**
     * Types that count toward budget spend.
     *
     * @return array<string>
     */
    public static function spendTypes(): array
    {
        return [self::Expense->value, self::Fee->value];
    }
}
