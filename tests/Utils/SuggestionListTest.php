<?php
namespace GraphQL\Tests\Utils;

use GraphQL\Executor\Values;
use GraphQL\Type\Definition\Type;
use GraphQL\Utils\Utils;
use GraphQL\Utils\Value;
use PHPUnit\Framework\TestCase;

class SuggestionListTest extends TestCase
{
    // DESCRIBE: suggestionList

    /**
     * @see it('Returns results when input is empty')
     */
    public function testResturnsResultsWhenInputIsEmpty() : void
    {
        $this->assertEquals(
            Utils::suggestionList('', ['a']),
            ['a']
        );
    }

    /**
     * @see it('Returns empty array when there are no options')
     */
    public function testReturnsEmptyArrayWhenThereAreNoOptions() : void
    {
        $this->assertEquals(
            Utils::suggestionList('input', []),
            []
        );
    }

    /**
     * @see it('Returns options sorted based on similarity')
     */
    public function testReturnsOptionsSortedBasedOnSimilarity() : void
    {
        $this->assertEquals(
            Utils::suggestionList('abc', ['a', 'ab', 'abc']),
            ['abc', 'ab']
        );
    }
}
