<?php

namespace App\Data\Transaction;

use App\Enums\Category;
use App\Enums\TransactionSort;
use App\Enums\TransactionType;
use App\Http\Requests\TransactionIndexRequest;
use App\Services\TagService;
use BackedEnum;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

/**
 * The list's server-side query: search text, filtered ids and the ordering —
 * the semantic internal form consumed by
 * TransactionListService::getTransactionsByCursorPage().
 *
 * The wire carries the frontend table's state structure (TanStack-shaped);
 * `fromRequest` is the single place translating that payload into this
 * form, so the service never knows the client's state vocabulary.
 */
final class TransactionListFilters extends Data
{
    /**
     * @param  list<int>  $accountIds
     * @param  list<string>  $categoryIds
     * @param  list<string>  $types
     * @param  list<string>  $tags
     */
    public function __construct(
        public readonly ?string $search = null,
        public readonly array $accountIds = [],
        public readonly array $categoryIds = [],
        public readonly array $types = [],
        public readonly array $tags = [],
        public readonly TransactionSort $sort = TransactionSort::Newest,
    ) {}

    /**
     * Translate the validated request payload — the frontend table's state
     * structure — into the semantic query.
     *
     * Expected shape (see TransactionIndexRequest):
     * `global_filter`, `filters: list<{id, value: list<int|string>}>`,
     * `sorting: list<{id, desc}>`.
     *
     * @throws ValidationException when filter values fail enum membership
     */
    public static function fromRequest(TransactionIndexRequest $request): self
    {
        try {
            $state = $request->validated();

            $accountIds = [];
            $categoryIds = [];
            $types = [];
            $tags = [];

            foreach ((array) ($state['filters'] ?? []) as $filter) {
                $values = array_values((array) ($filter['value'] ?? []));

                match ($filter['id']) {
                    'account_id' => $accountIds = array_map(intval(...), $values),
                    'category_id' => $categoryIds = self::enumValues($values, Category::class),
                    'type' => $types = self::enumValues($values, TransactionType::class),
                    'tags' => $tags = self::tagValues($values),
                    default => throw new InvalidArgumentException(
                        "Unknown transaction filter column [{$filter['id']}]."
                    ),
                };
            }

            return new self(
                search: isset($state['global_filter']) && $state['global_filter'] !== ''
                    ? (string) $state['global_filter']
                    : null,
                accountIds: $accountIds,
                categoryIds: $categoryIds,
                types: $types,
                tags: $tags,
                sort: self::sortFrom($state['sorting'] ?? []),
            );
        } catch (InvalidArgumentException) {
            throw ValidationException::withMessages([
                'filters' => 'The transaction filter state contains unknown values.',
            ]);
        }

    }

    /**
     * Maps a sort descriptor ({id, desc}, keyset column + direction) onto
     * the ordering enum. Absent descriptors default to newest.
     *
     * @param  list<array{id: string, desc: bool}>  $sorting
     */
    private static function sortFrom(array $sorting): TransactionSort
    {
        $descriptor = $sorting[0] ?? null;

        if ($descriptor === null) {
            return TransactionSort::Newest;
        }

        return match (true) {
            $descriptor['id'] === 'transaction_date' && $descriptor['desc'] => TransactionSort::Newest,
            $descriptor['id'] === 'transaction_date' => TransactionSort::Oldest,
            $descriptor['id'] === 'amount' && $descriptor['desc'] => TransactionSort::Highest,
            default => TransactionSort::Lowest,
        };
    }

    /**
     * Asserts every value is a normalized tag slug and returns the strings.
     *
     * @param  list<mixed>  $values
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when a value is not a slug
     */
    private static function tagValues(array $values): array
    {
        $values = array_map(strval(...), $values);

        foreach ($values as $value) {
            if (preg_match('/^[a-z0-9_]+$/', $value) !== 1 || mb_strlen($value) > TagService::MAX_TAG_LENGTH) {
                throw new InvalidArgumentException("Value [{$value}] is not a normalized tag slug.");
            }
        }

        return array_values($values);
    }

    /**
     * Asserts every value belongs to the enum and returns the raw strings —
     * the enum-backed columns store exactly these.
     *
     * @param  list<mixed>  $values
     *
     * @return list<string>
     *
     * @throws InvalidArgumentException when a value is not a case of the enum
     */
    private static function enumValues(array $values, string $enum): array
    {
        $allowed = array_map(fn (BackedEnum $case): string => (string) $case->value, $enum::cases());

        foreach ($values as $value) {
            if (! in_array((string) $value, $allowed, true)) {
                throw new InvalidArgumentException(
                    "Value [{$value}] is not a valid {$enum} case."
                );
            }
        }

        return array_map(strval(...), $values);
    }
}
