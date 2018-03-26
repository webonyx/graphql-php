<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;

class QuotedOrListTest extends \PHPUnit_Framework_TestCase
{
    // DESCRIBE: quotedOrList

    /**
     * @it Does not accept an empty list
     */
    public function testResturnsResultsWhenInputIsEmpty()
    {
        $this->setExpectedException(\LogicException::class);
        Utils::quotedOrList([]);
    }

    /**
     * @it Returns single quoted item
     */
    public function testReturnsSingleQuotedItem()
    {
        $this->assertEquals(
            '"A"',
            Utils::quotedOrList(['A'])
        );
    }

    /**
     * @it Returns two item list
     */
    public function testReturnsTwoItemList()
    {
        $this->assertEquals(
            '"A" or "B"',
            Utils::quotedOrList(['A', 'B'])
        );
    }

    /**
     * @it Returns comma separated many item list
     */
    public function testReturnsCommaSeparatedManyItemList()
    {
        $this->assertEquals(
            '"A", "B", or "C"',
            Utils::quotedOrList(['A', 'B', 'C'])
        );
    }

    /**
     * @it Limits to five items
     */
    public function testLimitsToFiveItems()
    {
        $this->assertEquals(
            '"A", "B", "C", "D", or "E"',
            Utils::quotedOrList(['A', 'B', 'C', 'D', 'E', 'F'])
        );
    }
}
