<?php

declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use Rector\Transform\Rector\String_\StringToClassConstantRector;
use RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap/app.php',
        __DIR__ . '/bootstrap/providers.php',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests', // TBD
    ])
    ->withParallel()
    ->withPhpSets(php85: true)
    ->withTypeCoverageLevel(10)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withCodingStyleLevel(10)
    ->withImportNames()
    ->withPreparedSets(instanceOf: true, earlyReturn: true)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130_WITHOUT_ATTRIBUTES,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        PestSetList::CODING_STYLE,
    ])
    ->withRules([
        // CoalesceToTernaryRector::class,
        // EnumCaseToPascalCaseRector::class,
        // NestedTernaryToMatchRector::class,
        DirnameDirConcatStringToDirectStringPathRector::class,
    ])
    ->withSkip([
        // AddOverrideAttributeToOverriddenMethodsRector::class,
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        DispatchToHelperFunctionsRector::class,
        SeparateMultiUseImportsRector::class,
        SleepFuncToSleepStaticCallRector::class,
        StringToClassConstantRector::class,
    ]);
