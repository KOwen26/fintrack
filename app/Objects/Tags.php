<?php

namespace App\Objects;

use App\Services\TagService;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Immutable list of normalized transaction-tag slugs. Persisted as JSON
 * through the inline cast; every write re-normalizes via TagService, so
 * the stored array is always canonical regardless of the write path.
 */
final readonly class Tags implements Castable
{
    /** @param list<string> $slugs */
    public function __construct(
        public readonly array $slugs,
    ) {}

    /**
     * @param  list<string>|null  $raw
     */
    public static function fromArray(?array $raw): ?self
    {
        $slugs = $raw !== null ? TagService::normalizeToNullable($raw) : null;

        return $slugs !== null ? new self($slugs) : null;
    }

    /** @return list<string> */
    public function toArray(): array
    {
        return $this->slugs;
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?Tags
            {
                if ($value === null) {
                    return null;
                }

                $decoded = json_decode($value, true);

                // Route through fromArray so hydration enforces the same
                // nullable contract as writes: empty/normalizes-to-empty
                // stored json reads back as null, never an empty Tags.
                return is_array($decoded) ? Tags::fromArray($decoded) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                // Normalize first so an empty Tags instance, an empty array, and
                // null all take the same "drop the column" path as writes.
                $slugs = match (true) {
                    $value === null => null,
                    $value instanceof Tags => $value->toArray(),
                    is_array($value) => $value,
                    default => throw new InvalidArgumentException('Tags value must be null, an array, or a Tags instance.'),
                };

                $normalized = $slugs === null ? null : TagService::normalizeToNullable($slugs);

                return $normalized === null ? null : json_encode($normalized);
            }
        };
    }
}
