<?php declare(strict_types=1);

namespace GraphQL\Tests\Utils;

use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;

class SuggestionListTest extends TestCase
{
    /**
     * @see describe('suggestionList')
     * @see it('Returns results when input is empty')
     */
    public function testReturnsResultsWhenInputIsEmpty(): void
    {
        self::assertEquals(Utils::suggestionList('', ['a']), ['a']);
    }

    /**
     * @see it('Returns empty array when there are no options')
     */
    public function testReturnsEmptyArrayWhenThereAreNoOptions(): void
    {
        self::assertEquals(Utils::suggestionList('input', []), []);
    }

    /**
     * @see it('Returns options with small lexical distance')
     */
    public function testReturnsOptionsWithSmallLexicalDistance(): void
    {
        self::assertEquals(Utils::suggestionList('greenish', ['green']), ['green']);
        self::assertEquals(Utils::suggestionList('green', ['greenish']), ['greenish']);
    }

    /**
     * @see it('Rejects options with distance that exceeds threshold')
     */
    public function testRejectsOptionsWithDistanceThatExceedsThreshold(): void
    {
        self::assertEquals(Utils::suggestionList('aaaa', ['aaab']), ['aaab']);
        self::assertEquals(Utils::suggestionList('aaaa', ['aabb']), ['aabb']);
        self::assertEquals(Utils::suggestionList('aaaa', ['abbb']), []);
        self::assertEquals(Utils::suggestionList('ab', ['ca']), []);
    }

    /**
     * @see it('Returns options with different case')
     */
    public function testReturnsOptionsWithDifferentCase(): void
    {
        self::assertEquals(
            Utils::suggestionList('verylongstring', ['VERYLONGSTRING']),
            ['VERYLONGSTRING']
        );
        self::assertEquals(
            Utils::suggestionList('VERYLONGSTRING', ['verylongstring']),
            ['verylongstring']
        );
        self::assertEquals(
            Utils::suggestionList('VERYLONGSTRING', ['VeryLongString']),
            ['VeryLongString']
        );
    }

    /**
     * @see it('Returns options with transpositions')
     */
    public function testReturnsOptionsWithTranspositions(): void
    {
        self::assertEquals(Utils::suggestionList('agr', ['arg']), ['arg']);
        self::assertEquals(Utils::suggestionList('214365879', ['123456789']), ['123456789']);
    }

    /**
     * @see it('Returns options sorted based on lexical distance')
     */
    public function testReturnsOptionsSortedBasedOnLexicalDistance(): void
    {
        self::assertEquals(
            Utils::suggestionList('abc', ['a', 'ab', 'abc']),
            ['abc', 'ab', 'a']
        );
    }

    /**
     * @see it('Returns options with the same lexical distance sorted lexicographically')
     */
    public function testReturnsOptionsWithTheSameLexicalDistanceSortedLexicographically(): void
    {
        self::assertEquals(
            Utils::suggestionList('a', ['az', 'ax', 'ay']),
            ['ax', 'ay', 'az']
        );
    }
}
