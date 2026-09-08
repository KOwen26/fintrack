<?php

namespace App\Helpers\TypeScript\Attributes;

use App\Helpers\TypeScript\WayfinderReference;
use Attribute;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptTypeAttributeContract;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptObject;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;

/**
 * Like LiteralTypeScriptType, but %placeholder% entries in $typeScript are
 * substituted with a class's dotted namespace path (derived from the given
 * ::class, so a rename/move stays in sync) instead of a hand-typed string.
 * Works for any class already typed in resources/js/wayfinder/types.d.ts
 * (models, enums, or other DTOs) - the placeholder is inlined directly
 * rather than re-declared as its own alias.
 */
#[Attribute]
class WayfinderTypeReference implements TypeScriptTypeAttributeContract
{
    /** @param array<string, class-string> $types */
    public function __construct(private readonly string | array $typeScript, private readonly array $types = [])
    {
    }

    public function getType(PhpClassNode $class): TypeScriptNode
    {
        if (is_string($this->typeScript)) {
            return $this->raw($this->typeScript);
        }

        $properties = collect($this->typeScript)
            ->map(fn (string $type, string $name): TypeScriptProperty => new TypeScriptProperty($name, $this->raw($type)))
            ->all();

        return new TypeScriptObject($properties);
    }

    protected function raw(string $typeScript): TypeScriptRaw
    {
        $additionalImports = [];

        foreach ($this->types as $placeholder => $referencedClass) {
            $additionalImports[WayfinderReference::root($referencedClass)] = WayfinderReference::additionalImport($referencedClass);

            $typeScript = str_replace("%{$placeholder}%", WayfinderReference::dottedPath($referencedClass), $typeScript);
        }

        return new TypeScriptRaw($typeScript, array_values($additionalImports));
    }
}
