<?php

namespace App\Helpers\TypeScript\Actions;

use App\Helpers\TypeScript\TypeScriptTransformer;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfig;

/**
 * Persists a transformed {@see TypesCollection} to the configured output file.
 *
 * This action sits between type resolution (discovering and transforming PHP
 * classes) and formatting (Prettier cleanup). Its job is simple:
 *
 * 1. Ensure the output directory exists.
 * 2. Offload formatting to the configured {@see Writer}
 *    (our custom {@see TypeDefinitionWriter}).
 * 3. Write the formatted string to the configured output path
 *    (`resources/js/types/generated.d.ts`).
 *
 * Why a dedicated action instead of inline code?
 *   Spatie's default transformer bakes file writing into its internal pipeline.
 *   By extracting it into its own action with a constructor-injected config,
 *   the transformer class becomes a thin orchestrator and each responsibility
 *   can be tested / replaced independently.
 *
 * @see TypeScriptTransformerConfig  Config provides writer and output path
 * @see TypeScriptTransformer               Orchestrator that calls this action
 */
class PersistTypesCollectionAction
{
    public function __construct(protected TypeScriptTransformerConfig $config) {}

    /**
     * Format the types collection via the configured Writer and write
     * the result to the configured output file path.
     */
    public function execute(TypesCollection $collection): void
    {
        $this->ensureOutputFileExists();

        $writer = $this->config->getWriter();

        file_put_contents(
            $this->config->getOutputFile(),
            $writer->format($collection),
        );
    }

    /**
     * Create the output directory recursively if it doesn't exist yet.
     */
    protected function ensureOutputFileExists(): void
    {
        if (! file_exists(pathinfo($this->config->getOutputFile(), PATHINFO_DIRNAME))) {
            mkdir(pathinfo($this->config->getOutputFile(), PATHINFO_DIRNAME), 0755, true);
        }
    }
}
