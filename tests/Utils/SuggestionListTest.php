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
        self::assertSame(
            ['a'],
            Utils::suggestionList('', ['a']),
        );
    }

    /**
     * @see it('Returns empty array when there are no options')
     */
    public function testReturnsEmptyArrayWhenThereAreNoOptions(): void
    {
        self::assertSame(
            [],
            Utils::suggestionList('input', []),
        );
    }

    /**
     * @see it('Returns options with small lexical distance')
     */
    public function testReturnsOptionsWithSmallLexicalDistance(): void
    {
        self::assertSame(
            ['green'],
            Utils::suggestionList('greenish', ['green']),
        );
        self::assertSame(
            ['greenish'],
            Utils::suggestionList('green', ['greenish']),
        );
    }

    /**
     * @see it('Rejects options with distance that exceeds threshold')
     */
    public function testRejectsOptionsWithDistanceThatExceedsThreshold(): void
    {
        self::assertSame(
            ['aaab'],
            Utils::suggestionList('aaaa', ['aaab']),
        );
        self::assertSame(
            ['aabb'],
            Utils::suggestionList('aaaa', ['aabb']),
        );
        self::assertSame(
            [],
            Utils::suggestionList('aaaa', ['abbb']),
        );
        self::assertSame(
            [],
            Utils::suggestionList('ab', ['ca']),
        );
    }

    /**
     * @see it('Returns options with different case')
     */
    public function testReturnsOptionsWithDifferentCase(): void
    {
        self::assertSame(
            ['VERYLONGSTRING'],
            Utils::suggestionList('verylongstring', ['VERYLONGSTRING']),
        );
        self::assertSame(
            ['verylongstring'],
            Utils::suggestionList('VERYLONGSTRING', ['verylongstring']),
        );
        self::assertSame(
            ['VeryLongString'],
            Utils::suggestionList('VERYLONGSTRING', ['VeryLongString']),
        );
    }

    /**
     * @see it('Returns options with transpositions')
     */
    public function testReturnsOptionsWithTranspositions(): void
    {
        self::assertSame(
            ['arg'],
            Utils::suggestionList('agr', ['arg']),
        );
        self::assertSame(
            ['123456789'],
            Utils::suggestionList('214365879', ['123456789']),
        );
    }

    /**
     * @see it('Returns options sorted based on lexical distance')
     */
    public function testReturnsOptionsSortedBasedOnLexicalDistance(): void
    {
        self::assertSame(
            ['abc', 'ab', 'a'],
            Utils::suggestionList('abc', ['a', 'ab', 'abc']),
        );
    }

    /**
     * @see it('Returns options with the same lexical distance sorted lexicographically')
     */
    public function testReturnsOptionsWithTheSameLexicalDistanceSortedLexicographically(): void
    {
        self::assertSame(
            ['ax', 'ay', 'az'],
            Utils::suggestionList('a', ['az', 'ax', 'ay']),
        );
    }
}
