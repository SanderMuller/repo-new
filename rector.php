<?php declare(strict_types=1);

use Pest\Rector\Set\PestSetList;
use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\ClassMethod\InlineArrayReturnAssignRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;

return RectorConfig::configure()
    ->withCache(
        cacheDirectory: './.cache/rector',
        cacheClass: FileCacheStorage::class,
        // Rector boots a PHPStan container, so it writes the same reflection and PHPDoc
        // cache entries PHPStan does - measured: 99% of them are entries PHPStan wants.
        // Sharing PHPStan's tmpDir keeps one copy instead of two.
        containerCacheDirectory: './.cache/phpstan',
    )
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        carbon: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
    )
    ->withAttributesSets()
    ->withImportNames()
    ->withFluentCallNewLine()
    ->withParallel(300, 15, 15)
    ->withMemoryLimit('3G')
    ->withPhpSets(php84: true)
    ->withSets(class_exists(PestSetList::class) ? [
        PestSetList::CODING_STYLE,
    ] : [])
    ->withSkip([
        NullToStrictStringFuncCallArgRector::class,
        AddArrowFunctionReturnTypeRector::class,
        InlineArrayReturnAssignRector::class,
        PrivatizeFinalClassMethodRector::class,
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
    ]);
