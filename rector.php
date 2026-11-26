<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withSkip([
        // "@return $this" documents the fluent contract of the interfaces.
        RemoveUselessReturnTagRector::class,
        // The empty setShutdown() override is the point of this test: a driver that ignores the shutdown.
        RemoveEmptyClassMethodRector::class => [__DIR__ . '/tests/Shutdown/DispatcherShutdownTest.php'],
    ]);
