<?php

namespace App\Helpers\TypeScript\Writers;

use Spatie\TypeScriptTransformer\Structures\TransformedType;
use Spatie\TypeScriptTransformer\Structures\TypesCollection;
use Spatie\TypeScriptTransformer\Writers\Writer;

/**
 * Custom writer that groups transformed types by namespace and emits
 * `declare namespace { ... }` blocks with dotted references.
 *
 * The default Spatie writer emits flat module-level exports. This project
 * follows the convention of wrapping types in `declare namespace` blocks
 * that mirror the PHP namespace hierarchy (converted to dots), producing
 * output like:
 *
 * ```typescript
 * declare namespace App.Data.Transaction {
 *   export type TransactionDetailData = { ... };
 * }
 * ```
 *
 * **Missing symbol resolution** (first pass)
 *   Before formatting, the writer iterates every type's missing symbols.
 *   For each missing symbol it checks if another type in the collection
 *   provides it; if yes it uses the provider's dotted name, otherwise it
 *   falls back to a raw `str_replace('\\', '.', ...)` conversion. This
 *   ensures that forward references like `{%App\Models\Account%}` are
 *   replaced with the correct `App.Models.Account` identifier.
 *
 * **Namespace grouping** (second pass)
 *   Types are split into two groups:
 *   - Root types (empty namespace) → emitted as plain `export type ...`
 *   - Namespaced types → emitted inside `declare namespace PHP.Dot.Path`
 *
 * Why a class-level docblock instead of per-method?
 *   The two public methods (`format`, `replacesSymbolsWithFullyQualifiedIdentifiers`)
 *   and the protected `groupByNamespace` work together as a single formatting
 *   pipeline. A class-level explanation avoids repeating context across
 *   every method.
 *
 * @see Writer  Interface this implements
 * @see config/typescript-transformer.php                Where this writer is registered
 */
class TypeDefinitionWriter implements Writer
{
    public function format(TypesCollection $collection): string
    {
        foreach ($collection as $type) {
            foreach ($type->missingSymbols->all() as $missingSymbol) {
                $found = $collection[$missingSymbol];

                $replacement = $found instanceof TransformedType
                    ? $found->getTypeScriptName(true)
                    : str_replace('\\', '.', $missingSymbol);

                $type->replaceSymbol($missingSymbol, $replacement);
            }
        }

        [$namespaces, $rootTypes] = $this->groupByNamespace($collection);

        $output = '';

        foreach ($namespaces as $namespace => $types) {
            asort($types);

            $output .= "declare namespace {$namespace} {" . PHP_EOL;

            $output .= implode(PHP_EOL, array_map(
                fn (TransformedType $type): string => "export {$type->toString()}",
                $types,
            ));

            $output .= PHP_EOL . '}' . PHP_EOL;
        }

        $output .= implode(PHP_EOL, array_map(
            fn (TransformedType $type): string => "export {$type->toString()}",
            $rootTypes,
        ));

        return $output;
    }

    /**
     * Signal to the transformer pipeline that we handle symbol replacement
     * ourselves (in the first pass of `format()`) rather than letting the
     * default writer do it with FQCNs.
     */
    public function replacesSymbolsWithFullyQualifiedIdentifiers(): bool
    {
        return true;
    }

    /**
     * Partition the collection into a namespace-keyed array and a root-level list.
     *
     * - PHP namespace `\` is converted to `.` for the TypeScript `declare namespace`.
     * - Inline types (anonymous / closure-like) are skipped since they have no
     *   stable namespace.
     * - Namespaces are sorted alphabetically for deterministic output.
     */
    protected function groupByNamespace(TypesCollection $collection): array
    {
        $namespaces = [];
        $rootTypes = [];

        foreach ($collection as $type) {
            if ($type->isInline) {
                continue;
            }

            $namespace = str_replace('\\', '.', $type->reflection->getNamespaceName());

            if (empty($namespace)) {
                $rootTypes[] = $type;

                continue;
            }

            array_key_exists($namespace, $namespaces)
                ? $namespaces[$namespace][] = $type
                : $namespaces[$namespace] = [$type];
        }

        ksort($namespaces);

        return [$namespaces, $rootTypes];
    }
}
