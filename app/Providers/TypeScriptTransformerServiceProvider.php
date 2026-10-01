<?php

namespace App\Providers;

use App\Helpers\TypeScript\AliasFlatModuleWriter;
use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\PrettierFormatter;
use Spatie\TypeScriptTransformer\References\ClassStringReference;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptReference;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Visitor\VisitorOperation;

/**
 * Configures spatie/laravel-typescript-transformer v3, which dropped config-file
 * support — this provider registers the TypeScriptTransformerConfig singleton
 * that the `typescript:transform` command resolves.
 *
 * Scans app/ for transformable classes (Data DTOs, #[TypeScript]-attributed) and
 * writes resources/js/types/generated.d.ts via AliasFlatModuleWriter.
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->transformDirectories(app_path())
            ->extension(new LaravelDataTypeScriptTransformerExtension)

            // Already Defaults
            // ->replaceType(DateTime::class, new TypeScriptString)
            // ->replaceType(DateTimeImmutable::class, new TypeScriptString)
            ->outputDirectory(resource_path('js/types'))
            ->withoutManifest()

            // Default writer
            // ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->writer(new AliasFlatModuleWriter(path: 'generated.d.ts'))
            ->connectedVisitorHook(
                // VisitorClosure reflects on the closure source; first-class
                // callable syntax (...) is not supported for this.
                fn (TypeScriptReference $node): VisitorOperation => $this->replaceUntransformedReference($node),
                allowedNodes: [TypeScriptReference::class],
            )
            ->formatter(PrettierFormatter::class);
    }

    /**
     * Rewrite references to untransformed classes (models, enums): they would
     * otherwise render as `undefined`. Emitted as dotted names instead, e.g.
     * `App.Models.Account`.
     */
    protected function replaceUntransformedReference(TypeScriptReference $node): VisitorOperation
    {
        if ($node->referenced === null && $node->reference instanceof ClassStringReference) {
            return VisitorOperation::replace(
                new TypeScriptRaw(str_replace('\\', '.', $node->reference->classString)),
            );
        }

        return VisitorOperation::keep();
    }
}
