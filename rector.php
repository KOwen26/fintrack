<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Coalesce\CoalesceToTernaryRector;
use Rector\CodeQuality\Rector\Concat\DirnameDirConcatStringToDirectStringPathRector;
use Rector\CodingStyle\Rector\Assign\NestedTernaryToMatchRector;
use Rector\CodingStyle\Rector\Enum_\EnumCaseToPascalCaseRector;
use Rector\CodingStyle\Rector\Use_\SeparateMultiUseImportsRector;
use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\Transform\Rector\String_\StringToClassConstantRector;
use RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector;
use RectorLaravel\Rector\StaticCall\DispatchToHelperFunctionsRector;
use RectorLaravel\Set\LaravelLevelSetList;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/bootstrap',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        // __DIR__ . '/tests', // TBD
    ])
    ->withParallel()
    ->withPhpSets(php84: true)
    ->withTypeCoverageLevel(10)
    ->withDeadCodeLevel(10)
    ->withCodeQualityLevel(10)
    ->withCodingStyleLevel(10)
    ->withImportNames()
    ->withPreparedSets(instanceOf: true, earlyReturn: true)
    ->withSets([
        LaravelLevelSetList::UP_TO_LARAVEL_130_WITHOUT_ATTRIBUTES,
        LaravelSetList::LARAVEL_CODE_QUALITY,
    ])
    ->withRules([
        CoalesceToTernaryRector::class,
        DirnameDirConcatStringToDirectStringPathRector::class,
        EnumCaseToPascalCaseRector::class,
        NestedTernaryToMatchRector::class,
    ])
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        DispatchToHelperFunctionsRector::class,
        SeparateMultiUseImportsRector::class,
        SleepFuncToSleepStaticCallRector::class,
        StringToClassConstantRector::class,
    ]);
