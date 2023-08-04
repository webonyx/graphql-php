<?php declare(strict_types=1);

use Rector\CodeQuality\Rector\Array_\CallableThisArrayToAnonymousFunctionRector;
use Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector;
use Rector\CodeQuality\Rector\Concat\JoinStringConcatRector;
use Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector;
use Rector\CodeQuality\Rector\Switch_\SwitchTrueToIfRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector;
use Rector\DeadCode\Rector\If_\RemoveDeadInstanceOfRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\PHPUnit\Rector\Class_\AddSeeTestAnnotationRector;
use Rector\PHPUnit\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Rector\MethodCall\AssertEqualsToSameRector;
use Rector\PHPUnit\Rector\MethodCall\AssertIssetToSpecificMethodRector;
use Rector\PHPUnit\Rector\MethodCall\AssertPropertyExistsRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        PHPUnitSetList::PHPUNIT_90,
        PHPUnitSetList::PHPUNIT_91,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
        PHPUnitSetList::PHPUNIT_EXCEPTION,
        PHPUnitSetList::PHPUNIT_SPECIFIC_METHOD,
        PHPUnitSetList::REMOVE_MOCKS,
    ]);
    $rectorConfig->skip([
        AddSeeTestAnnotationRector::class, // We do not bundle tests
        PreferPHPUnitThisCallRector::class, // Prefer self::
        CallableThisArrayToAnonymousFunctionRector::class, // Callable in array form is shorter and more efficient
        IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        JoinStringConcatRector::class => [
            __DIR__ . '/tests',
        ],
        LocallyCalledStaticMethodToNonStaticRector::class, // static methods are fine
        UnusedForeachValueToArrayKeysRector::class, // Less efficient
        RemoveAlwaysTrueIfConditionRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        RemoveDeadInstanceOfRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        RemoveNonExistingVarAnnotationRector::class, // Sometimes false-positive
        RemoveUnusedPrivatePropertyRector::class, // TODO reintroduce when https://github.com/rectorphp/rector-src/pull/4491 is released
        AssertPropertyExistsRector::class, // Uses deprecated PHPUnit methods
        AssertIssetToSpecificMethodRector::class => [
            __DIR__ . '/tests/Utils/MixedStoreTest.php', // Uses keys that are not string or int
        ],
        AssertEqualsToSameRector::class => [
            __DIR__ . '/tests/TestCaseBase.php',
        ],
        SwitchTrueToIfRector::class, // More expressive in some cases
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
