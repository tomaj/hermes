<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        \Rector\CodingStyle\Rector\FuncCall\StrictArraySearchRector::class,
        \Rector\DeadCode\Rector\FunctionLike\NarrowWideUnionReturnTypeRector::class,
        \Rector\CodingStyle\Rector\String_\SimplifyQuoteEscapeRector::class,
        \Rector\DeadCode\Rector\Cast\RecastingRemovalRector::class,
        \Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector::class,
        \Rector\CodingStyle\Rector\If_\NullableCompareToNullRector::class,
        \Rector\CodingStyle\Rector\Assign\SplitDoubleAssignRector::class,
        \Rector\DeadCode\Rector\PropertyProperty\RemoveNullPropertyInitializationRector::class,
        \Rector\EarlyReturn\Rector\StmtsAwareInterface\ReturnEarlyIfVariableRector::class,
    ])
     ->withSets([
         LevelSetList::UP_TO_PHP_71,
         SetList::DEAD_CODE,
         SetList::CODING_STYLE,
         SetList::EARLY_RETURN,
     ]);
