<?php

namespace App\Objects;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use InvalidArgumentException;

/**
 * Keyset cursor into the newest-first transaction stream (`transaction_date
 * DESC, id DESC`). A cursor pins the (date, id) of the last already-loaded
 * row, so "load more" keeps its position even when new inserts arrive above
 * the window — offset pagination would re-serve those rows.
 *
 * `date` holds the SQL-comparable `Y-m-d H:i:s` form of the stored column,
 * mirroring `DatePeriod::toRange()` — models store `transaction_date` in the
 * connection's datetime format, so ISO strings would mis-compare lexicographically.
 */
final readonly class TransactionCursor
{
    public function __construct(public string $date, public int $id) {}

    /**
     * Parse a wire cursor: `<ISO datetime>|<id>` — the `transaction_date` and
     * `id` of the last loaded row. Absent or empty cursors parse to null.
     *
     * @throws InvalidFormatException|InvalidArgumentException on malformed input
     */
    public static function fromWire(?string $cursor): ?self
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $parts = explode('|', $cursor);

        if (count($parts) !== 2 || in_array('', $parts, true)) {
            throw new InvalidArgumentException('Transaction cursor must be "<datetime>|<id>".');
        }

        [$date, $id] = $parts;

        $timestamp = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($timestamp === false) {
            throw new InvalidArgumentException('Transaction cursor id must be a positive integer.');
        }

        return new self(
            CarbonImmutable::parse($date)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d H:i:s'),
            $timestamp,
        );
    }
}
