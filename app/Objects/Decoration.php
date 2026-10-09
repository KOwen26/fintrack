<?php

namespace App\Objects;

use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Icon/color pair attached to display entities (accounts, providers,
 * category presets). Immutable; persisted as JSON through the inline cast.
 */
#[TypeScript]
final readonly class Decoration implements Castable
{
    public function __construct(
        public ?string $icon = null,
        public ?string $color = null,
    ) {}

    /** @param array{icon?: ?string, color?: ?string}|null $decorations */
    public static function fromArray(?array $decorations): self
    {
        return new self(
            icon: $decorations['icon'] ?? null,
            color: $decorations['color'] ?? null,
        );
    }

    /** @return array{icon: ?string, color: ?string} */
    public function toArray(): array
    {
        return [
            'icon' => $this->icon,
            'color' => $this->color,
        ];
    }

    public static function castUsing(array $arguments): CastsAttributes
    {
        return new class implements CastsAttributes
        {
            public function get(Model $model, string $key, mixed $value, array $attributes): ?Decoration
            {
                if ($value === null) {
                    return null;
                }

                $decoded = json_decode($value, true);

                return is_array($decoded) ? Decoration::fromArray($decoded) : null;
            }

            public function set(Model $model, string $key, mixed $value, array $attributes): ?string
            {
                if ($value === null) {
                    return null;
                }

                if ($value instanceof Decoration) {
                    return json_encode($value->toArray());
                }

                if (is_array($value)) {
                    return json_encode(Decoration::fromArray($value)->toArray());
                }

                throw new InvalidArgumentException('Decoration value must be null, an array, or a Decoration instance.');
            }
        };
    }
}
