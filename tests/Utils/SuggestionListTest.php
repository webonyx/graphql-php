<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;

class SuggestionListTest extends \PHPUnit_Framework_TestCase
{
    // DESCRIBE: suggestionList

    /**
     * @it Returns results when input is empty
     */
    public function testResturnsResultsWhenInputIsEmpty()
    {
        $this->assertEquals(
            Utils::suggestionList('', ['a']),
            ['a']
        );
    }

    /**
     * @it Returns empty array when there are no options
     */
    public function testReturnsEmptyArrayWhenThereAreNoOptions()
    {
        $this->assertEquals(
            Utils::suggestionList('input', []),
            []
        );
    }

    /**
     * @it Returns options sorted based on similarity
     */
    public function testReturnsOptionsSortedBasedOnSimilarity()
    {
        $this->assertEquals(
            Utils::suggestionList('abc', ['a', 'ab', 'abc']),
            ['abc', 'ab']
        );
    }
}
