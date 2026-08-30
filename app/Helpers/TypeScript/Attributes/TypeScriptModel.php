<?php

namespace App\Helpers\TypeScript\Attributes;

use Attribute;
use phpDocumentor\Reflection\Type;
use phpDocumentor\Reflection\TypeResolver;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptTransformableAttribute;

/**
 * Map a DTO property to an Eloquent model type in the generated TypeScript.
 *
 * Vanilla `#[TypeScriptType]` requires raw string FQCNs with double backslashes,
 * which is tedious and error-prone:
 *   #[TypeScriptType('App\\Models\\Account')]
 *   #[TypeScriptType('App\\Models\\Category|null')]
 *
 * This attribute wraps the same underlying phpDocumentor\TypeResolver but
 * gives an IDE-friendly `::class` API, with a dedicated `nullable` parameter:
 *   #[TypeScriptModel(Account::class)]
 *   #[TypeScriptModel(Category::class, nullable: true)]
 *
 * Why nullable is explicit instead of concatenated inline:
 *   PHP 8 attribute arguments must be constant expressions, so `Account::class`
 *   is valid but `Account::class . '|null'` (string concatenation) is not.
 *
 * How it works:
 *   getType() resolves to a phpDocumentor\Reflection\Type that the transformer
 *   pipeline converts to a `{%App\Models\Category%}` placeholder. The custom
 *   TypeDefinitionWriter then replaces placeholders with dotted references
 *   (e.g. `App.Models.Category | null`), matching the project's convention
 *   for undeclared external types.
 */
#[Attribute]
class TypeScriptModel implements TypeScriptTransformableAttribute
{
    public function __construct(
        private readonly string $class,
        private readonly bool $nullable = false,
    ) {}

    public function getType(): Type
    {
        $type = $this->nullable
            ? "{$this->class}|null"
            : $this->class;

        return (new TypeResolver)->resolve($type);
    }
}
