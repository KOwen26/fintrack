<?php

namespace App\Data\Transaction;

use App\Enums\TransactionSort;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * Keyset cursor into the transaction stream, pinned to the ordering it was
 * cut under (`TransactionSort`). A cursor holds the (value, id) of the last
 * already-loaded row, so "load more" keeps its position even when new
 * inserts arrive above the window — offset pagination would re-serve those
 * rows.
 *
 * `date` cursors hold the SQL-comparable `Y-m-d H:i:s` form of the stored
 * column, mirroring `DatePeriod::toRange()` — models store `transaction_date`
 * in the connection's datetime format, so ISO strings would mis-compare
 * lexicographically. A cursor flavor that mismatches the requested sort would
 * silently produce wrong pages and is rejected at parse time. The client
 * treats the wire form as opaque and echoes `transactions_meta.next_cursor`.
 */
final class TransactionListCursor extends Data
{
    public function __construct(
        public readonly string $value,
        public readonly int $id,
    ) {}

    /**
     * Pulls the `cursor` query parameter and parses it. A malformed or
     * flavor-mismatched cursor aborts with 400 — silent fallbacks would hide
     * client bugs.
     */
    public static function fromRequest(Request $request, TransactionSort $sort): ?self
    {
        try {
            return self::parse($request->input('cursor'), $sort);
        } catch (InvalidFormatException | InvalidArgumentException) {
            abort(400, 'Malformed transaction cursor.');
        }
    }

    /**
     * Parse a raw cursor string: `<flavor>|<value>|<id>` — the keyset flavor
     * ("date" or "amount") plus the pinned column's value and the id of the
     * last loaded row. Absent or empty cursors parse to null.
     *
     * @throws InvalidFormatException|InvalidArgumentException on malformed or flavor-mismatched input
     */
    private static function parse(?string $cursor, TransactionSort $sort): ?self
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $parts = explode('|', $cursor);

        if (count($parts) !== 3 || in_array('', $parts, true)) {
            throw new InvalidArgumentException('Transaction cursor must be "<flavor>|<value>|<id>".');
        }

        [$flavor, $value, $id] = $parts;

        if ($flavor !== $sort->cursorPrefix()) {
            throw new InvalidArgumentException('Transaction cursor flavor does not match the requested sort.');
        }

        $rowId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($rowId === false) {
            throw new InvalidArgumentException('Transaction cursor id must be a positive integer.');
        }

        if ($sort->cursorPrefix() === 'date') {
            $value = CarbonImmutable::parse($value)
                ->timezone(config('app.timezone'))
                ->format('Y-m-d H:i:s');
        }

        return new self($value, $rowId);
    }

    /** Wire form of the last loaded row under `sort`. */
    public static function toWire(TransactionSort $sort, string $value, int $id): string
    {
        return $sort->cursorPrefix() . '|' . $value . '|' . $id;
    }
}
