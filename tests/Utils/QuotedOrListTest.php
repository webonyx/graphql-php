<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class QuotedOrListTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertSame(
            '',
            Utils::quotedOrList([])
        );
    }

    public function testSingleItem(): void
    {
        self::assertSame(
            '"A"',
            Utils::quotedOrList(['A'])
        );
    }

    public function testTwoItems(): void
    {
        self::assertSame(
            '"A" or "B"',
            Utils::quotedOrList(['A', 'B'])
        );
    }

    public function testManyItems(): void
    {
        self::assertSame(
            '"A", "B", or "C"',
            Utils::quotedOrList(['A', 'B', 'C'])
        );
    }

    public function testLimitsToFiveItems(): void
    {
        self::assertSame(
            '"A", "B", "C", "D", or "E"',
            Utils::quotedOrList(['A', 'B', 'C', 'D', 'E', 'F'])
        );
    }
}
