<?php

namespace App\Helpers\TypeScript;

use Spatie\TypeScriptTransformer\Attributes\AdditionalImport;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptRaw;

/**
 * Shared logic for pointing a PHP class at its already-generated Wayfinder
 * ambient type (resources/js/wayfinder/types.d.ts) instead of having
 * typescript-transformer derive/declare it itself.
 */
class WayfinderReference
{
    public static function dottedPath(string $class): string
    {
        return str_replace('\\', '.', trim($class, '\\'));
    }

    public static function root(string $class): string
    {
        return str_starts_with(trim($class, '\\'), 'App\\') ? 'App' : 'Modules';
    }

    public static function additionalImport(string $class): AdditionalImport
    {
        return new AdditionalImport('@/wayfinder/types', self::root($class));
    }

    public static function node(string $class): TypeScriptRaw
    {
        return new TypeScriptRaw(self::dottedPath($class), [self::additionalImport($class)]);
    }
}
