<?php

namespace App\Enums;

/**
 * Orderings the transaction index can be served under. Each ordering is
 * pinned to a keyset column — the cursor flavor must match it.
 */
enum TransactionSort: string
{
    case Newest = 'newest';
    case Oldest = 'oldest';
    case Highest = 'highest';
    case Lowest = 'lowest';

    /** The keyset column this ordering is pinned to. */
    public function column(): string
    {
        return $this === self::Newest || $this === self::Oldest
            ? 'transaction_date'
            : 'amount';
    }

    public function direction(): string
    {
        return $this === self::Newest || $this === self::Highest ? 'desc' : 'asc';
    }

    /** Wire prefix identifying the cursor flavor for this ordering. */
    public function cursorPrefix(): string
    {
        return $this === self::Newest || $this === self::Oldest ? 'date' : 'amount';
    }
}
