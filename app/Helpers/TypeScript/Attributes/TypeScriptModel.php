<?php

namespace App\Helpers\TypeScript\Attributes;

use Attribute;
use Spatie\TypeScriptTransformer\Actions\TranspilePhpStanTypeToTypeScriptNodeAction;
use Spatie\TypeScriptTransformer\Attributes\TypeScriptTypeAttributeContract;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\TypeResolvers\DocTypeResolver;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;

/**
 * Map a DTO property to an Eloquent model type in the generated TypeScript.
 *
 * Integrated alternative to `#[TypeScriptType('App\\Models\\Account')]`:
 *   #[TypeScriptModel(Account::class)]
 *   #[TypeScriptModel(Category::class, nullable: true)]
 *
 * The emitted reference becomes a dotted undeclared type (e.g.
 * `App.Models.Account`) via the unresolved-reference hook in
 * TypeScriptTransformerServiceProvider.
 */
#[Attribute]
class TypeScriptModel implements TypeScriptTypeAttributeContract
{
    public function __construct(
        private readonly string $class,
        private readonly bool $nullable = false,
    ) {}

    public function getType(PhpClassNode $class): TypeScriptNode
    {
        $type = $this->nullable
            ? "{$this->class}|null"
            : $this->class;

        return (new TranspilePhpStanTypeToTypeScriptNodeAction)->execute(
            (new DocTypeResolver)->type($type),
            $class,
        );
    }
}
