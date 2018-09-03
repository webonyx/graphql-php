<?php

declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Utils\Utils;
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
