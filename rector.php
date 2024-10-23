<?php declare(strict_types=1);

return static function (Rector\Config\RectorConfig $rectorConfig): void {
    $rectorConfig->sets([
        Rector\Set\ValueObject\SetList::CODE_QUALITY,
        Rector\Set\ValueObject\SetList::DEAD_CODE,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_60,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_70,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_80,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_90,
        Rector\PHPUnit\Set\PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
    $rectorConfig->skip([
        Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector::class, // Prefer self::
        Rector\PHPUnit\PHPUnit60\Rector\ClassMethod\AddDoesNotPerformAssertionToNonAssertingTestRector::class, // False-positive
        Rector\CodeQuality\Rector\Isset_\IssetOnPropertyObjectToPropertyExistsRector::class, // isset() is nice when moving towards typed properties
        Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class, // Unnecessarily complex with PHPStan
        Rector\CodeQuality\Rector\Concat\JoinStringConcatRector::class => [
            __DIR__ . '/tests', // Sometimes more readable for long strings
        ],
        Rector\CodeQuality\Rector\ClassMethod\LocallyCalledStaticMethodToNonStaticRector::class, // static methods are fine
        Rector\CodeQuality\Rector\Foreach_\UnusedForeachValueToArrayKeysRector::class, // Less efficient
        Rector\DeadCode\Rector\If_\RemoveAlwaysTrueIfConditionRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        Rector\DeadCode\Rector\If_\RemoveDeadInstanceOfRector::class, // Sometimes necessary to prove runtime behaviour matches defined types
        Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector::class, // Sometimes false-positive
        Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector::class, // TODO reintroduce when https://github.com/rectorphp/rector-src/pull/4491 is released
        Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertPropertyExistsRector::class, // Uses deprecated PHPUnit methods
        Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertIssetToSpecificMethodRector::class => [
            __DIR__ . '/tests/Utils/MixedStoreTest.php', // Uses keys that are not string or int
        ],
        Rector\PHPUnit\CodeQuality\Rector\MethodCall\AssertEqualsToSameRector::class => [
            __DIR__ . '/tests/TestCaseBase.php', // Array output may differ between tested PHP versions, assertEquals smooths over this
        ],
        Rector\CodeQuality\Rector\Switch_\SwitchTrueToIfRector::class, // More expressive in some cases
    ]);
    $rectorConfig->paths([
        __DIR__ . '/benchmarks',
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
