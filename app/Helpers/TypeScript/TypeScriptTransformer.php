<?php

namespace App\Helpers\TypeScript;

use App\Helpers\TypeScript\Actions\PersistTypesCollectionAction;
use Spatie\TypeScriptTransformer\Actions\FormatTypeScriptAction;
use Spatie\TypeScriptTransformer\Actions\ResolveTypesCollectionAction;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\TypeScriptTransformer as BaseTypeScriptTransformer;
use Symfony\Component\Finder\Finder;

/**
 * Custom TypeScript transformer that overrides Spatie's pipeline to plug in
 * a custom writer and persistence workflow.
 *
 * The default Spatie transformer couples resolution, formatting, and output
 * writing into a rigid flow. This version explicitly orchestrates three
 * discrete phases:
 *
 * 1. **Resolve** — {@see ResolveTypesCollectionAction} scans the configured
 *    paths with Symfony Finder, discovers all `#[TypeScript]`-attributed and
 *    `@typescript`-annotated classes, and runs each through the configured
 *    transformer pipeline (SpatieStateTransformer, EnumTransformer,
 *    DtoTransformer, etc.) to produce a {@see TypesCollection}.
 *
 * 2. **Persist** — {@see PersistTypesCollectionAction} takes the collection,
 *    delegates formatting to the configured {@see Writer} (our custom
 *    {@see TypeDefinitionWriter}), and writes the result to the configured
 *    output file (`resources/js/types/generated.d.ts`).
 *
 * 3. **Format** — {@see FormatTypeScriptAction} runs Prettier on the written
 *    file to produce clean, consistent output.
 *
 * Why a custom class instead of configuration?
 *   The base transformer runs a single internal pipeline. To inject
 *   TypeDefinitionWriter into the writer slot and skip the default writer
 *   internals, we re-implement `transform()` with our own action wiring.
 *
 * @see BaseTypeScriptTransformer    Base implementation
 * @see config/typescript-transformer.php                        Configuration
 */
class TypeScriptTransformer extends BaseTypeScriptTransformer
{
    public function transform(): TypesCollection
    {
        $typesCollection = (new ResolveTypesCollectionAction(
            new Finder,
            $this->config,
        ))->execute();

        (new PersistTypesCollectionAction($this->config))->execute($typesCollection);

        (new FormatTypeScriptAction($this->config))->execute();

        return $typesCollection;
    }
}
