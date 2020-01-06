<?php

declare(strict_types=1);

namespace GraphQL\Tests\PHPUnit;

use ArrayAccess;
use GraphQL\Tests\PHPUnit\Constraint\ArraySubset;
use PHPUnit\Framework\Assert as PhpUnitAssert;
use PHPUnit\Framework\InvalidArgumentException;
use function is_array;

/**
 * Port from dms/phpunit-arraysubset-asserts
 */
trait ArraySubsetAsserts
{
    /**
     * Asserts that an array has a specified subset.
     *
     * @param ArrayAccess|mixed[] $subset
     * @param ArrayAccess|mixed[] $array
     */
    public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = '') : void
    {
        if (! (is_array($subset) || $subset instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                1,
                'array or ArrayAccess'
            );
        }
        if (! (is_array($array) || $array instanceof ArrayAccess)) {
            throw InvalidArgumentException::create(
                2,
                'array or ArrayAccess'
            );
        }
        $constraint = new ArraySubset($subset, $checkForObjectIdentity);
        PhpUnitAssert::assertThat($array, $constraint, $message);
    }
}
