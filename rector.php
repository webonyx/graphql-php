<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\If_\RemoveDeadInstanceOfRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
    ]);
    $rectorConfig->skip([
        CallableThisArrayToAnonymousFunctionRector::class, // Callable in array form is shorter and more efficient
        IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        UnusedForeachValueToArrayKeysRector::class, // Less efficient
        RemoveAlwaysTrueIfConditionRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        RemoveDeadInstanceOfRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        RemoveNonExistingVarAnnotationRector::class, // Sometimes false-positive
    ]);
    $rectorConfig->paths([
        __DIR__ . '/examples',
        __DIR__ . '/phpstan',
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/.php-cs-fixer.php',
        __DIR__ . '/generate-class-reference.php',
        __DIR__ . '/rector.php',
    ]);
    $rectorConfig->phpstanConfig(__DIR__ . '/phpstan.neon');
};
