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

                return is_array($decoded) ? new Tags(TagService::normalize($decoded)) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof Tags) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode(TagService::normalize($value));
                }

                throw new InvalidArgumentException('Tags value must be null, an array, or a Tags instance.');
            }
        };
    }
}
