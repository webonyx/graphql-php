<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use PHPUnit\Framework\TestCase;

abstract class TestCaseBase extends TestCase
{
    /**
     * Useful to test code with no observable behaviour other than not crashing.
     *
     * In contrast to PHPUnit's native method, this lets the test case count towards coverage.
     *
     * @see TestCase::expectNotToPerformAssertions()
     */
    public static function assertDidNotCrash(): void
    {
        // @phpstan-ignore-next-line this truism is required to prevent a PHPUnit warning
        self::assertTrue(true);
    }
}
