<?php

namespace App\Helpers\TypeScript;

use Spatie\TypeScriptTransformer\Actions\ResolveRelativePathAction;

/**
 * The package's own ResolveRelativePathAction always computes a `./` or `../`
 * path, it has no concept of bundler path aliases. Paths already written as an
 * alias (e.g. `@/wayfinder/types`) are passed through verbatim instead of being
 * re-diffed against the writer's output location.
 */
class AliasAwareResolveRelativePathAction extends ResolveRelativePathAction
{
    public function execute(string $from, string $to): ?string
    {
        if (str_starts_with($to, '@/')) {
            return $to;
        }

        return parent::execute($from, $to);
    }
}
