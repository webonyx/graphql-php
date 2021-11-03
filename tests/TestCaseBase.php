<?php

declare(strict_types=1);

namespace GraphQL\Tests;

use PHPUnit\Framework\TestCase;

abstract class TestCaseBase extends TestCase
{
    public static function assertDidNotCrash(): void
    {
        self::assertTrue(true);
    }
}
