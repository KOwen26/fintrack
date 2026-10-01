<?php

namespace App\Helpers\TypeScript;

use Spatie\TypeScriptTransformer\Actions\ResolveImportsAndResolvedReferenceMapAction;
use Spatie\TypeScriptTransformer\Writers\FlatModuleWriter;

class AliasFlatModuleWriter extends FlatModuleWriter
{
    public function __construct(public string $path = 'types.ts')
    {
        parent::__construct($path);

        $this->resolveImportsAndResolvedReferenceMapAction = new ResolveImportsAndResolvedReferenceMapAction(
            new AliasAwareResolveRelativePathAction,
        );
    }
}
