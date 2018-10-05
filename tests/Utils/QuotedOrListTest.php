<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Utils\Utils;
use LogicException;
use PHPUnit\Framework\TestCase;

class QuotedOrListTest extends TestCase
{
    // DESCRIBE: quotedOrList
    /**
     * @see it('Does not accept an empty list')
     */
    public function testResturnsResultsWhenInputIsEmpty() : void
    {
        $this->expectException(LogicException::class);
        Utils::quotedOrList([]);
    }

    /**
     * @see it('Returns single quoted item')
     */
    public function testReturnsSingleQuotedItem() : void
    {
        self::assertEquals(
            '"A"',
            Utils::quotedOrList(['A'])
        );
    }

    /**
     * @see it('Returns two item list')
     */
    public function testReturnsTwoItemList() : void
    {
        self::assertEquals(
            '"A" or "B"',
            Utils::quotedOrList(['A', 'B'])
        );
    }

    /**
     * @see it('Returns comma separated many item list')
     */
    public function testReturnsCommaSeparatedManyItemList() : void
    {
        self::assertEquals(
            '"A", "B", or "C"',
            Utils::quotedOrList(['A', 'B', 'C'])
        );
    }

    /**
     * @see it('Limits to five items')
     */
    public function testLimitsToFiveItems() : void
    {
        self::assertEquals(
            '"A", "B", "C", "D", or "E"',
            Utils::quotedOrList(['A', 'B', 'C', 'D', 'E', 'F'])
        );
    }
}
