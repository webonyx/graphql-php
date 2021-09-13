<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\BlockString;
use PHPUnit\Framework\TestCase;

use function implode;

class BlockStringTest extends TestCase
{
    private static function joinLines(string ...$args): string
    {
        return implode("\n", $args);
    }

    // describe('dedentBlockStringValue')

    /**
     * @see it('removes uniform indentation from a string')
     */
    public function testRemovesUniformIndentationFromAString(): void
    {
        $rawValue = self::joinLines(
            '',
            '    Hello,',
            '      World!',
            '',
            '    Yours,',
            '      GraphQL.',
        );
        self::assertEquals(
            self::joinLines('Hello,', '  World!', '', 'Yours,', '  GraphQL.'),
            BlockString::dedentValue($rawValue)
        );
    }

    /**
     * @see it('removes empty leading and trailing lines')
     */
    public function testRemovesEmptyLeadingAndTrailingLines(): void
    {
        $rawValue = self::joinLines(
            '',
            '',
            '    Hello,',
            '      World!',
            '',
            '    Yours,',
            '      GraphQL.',
            '',
            '',
        );
        self::assertEquals(
            self::joinLines('Hello,', '  World!', '', 'Yours,', '  GraphQL.'),
            BlockString::dedentValue($rawValue)
        );
    }

    /**
     * @see it('removes blank leading and trailing lines')
     */
    public function testRemovesBlankLeadingAndTrailingLines(): void
    {
        $rawValue = self::joinLines(
            '  ',
            '        ',
            '    Hello,',
            '      World!',
            '',
            '    Yours,',
            '      GraphQL.',
            '        ',
            '  ',
        );
        self::assertEquals(
            self::joinLines('Hello,', '  World!', '', 'Yours,', '  GraphQL.'),
            BlockString::dedentValue($rawValue)
        );
    }

    /**
     * @see it('retains indentation from first line')
     */
    public function testRetainsIndentationFromFirstLine(): void
    {
        $rawValue = self::joinLines(
            '    Hello,',
            '      World!',
            '',
            '    Yours,',
            '      GraphQL.',
        );
        self::assertEquals(
            self::joinLines('    Hello,', '  World!', '', 'Yours,', '  GraphQL.'),
            BlockString::dedentValue($rawValue)
        );
    }

    /**
     * @see it('does not alter trailing spaces')
     */
    public function testDoesNotAlterTrailingSpaces(): void
    {
        $rawValue = self::joinLines(
            '               ',
            '    Hello,     ',
            '      World!   ',
            '               ',
            '    Yours,     ',
            '      GraphQL. ',
            '               ',
        );
        self::assertEquals(
            self::joinLines(
                'Hello,     ',
                '  World!   ',
                '           ',
                'Yours,     ',
                '  GraphQL. ',
            ),
            BlockString::dedentValue($rawValue)
        );
    }

    // describe('getBlockStringIndentation')

    /**
     * @see it('returns zero for an empty string')
     */
    public function testReturnsZeroForAnEmptyString(): void
    {
        self::assertEquals(0, BlockString::getIndentation(''));
    }

    /**
     * @see it('do not take first line into account')
     */
    public function testDoNotTakeFirstLineIntoAccount(): void
    {
        self::assertEquals(0, BlockString::getIndentation('  a'));
        self::assertEquals(2, BlockString::getIndentation(" a\n  b"));
    }

    /**
     * @see it('returns minimal indentation length')
     */
    public function testReturnsMinimalIndentationLength(): void
    {
        self::assertEquals(1, BlockString::getIndentation("\n a\n  b"));
        self::assertEquals(1, BlockString::getIndentation("\n  a\n b"));
        self::assertEquals(0, BlockString::getIndentation("\n  a\n b\nc"));
    }

    /**
     * @see it('count both tab and space as single character')
     */
    public function testCountBothTabAndSpaceAsSingleCharacter(): void
    {
        self::assertEquals(1, BlockString::getIndentation("\n\ta\n          b"));
        self::assertEquals(2, BlockString::getIndentation("\n\t a\n          b"));
        self::assertEquals(3, BlockString::getIndentation("\n \t a\n          b"));
    }

    /**
     * @see it('do not take empty lines into account')
     */
    public function testDoNotTakeEmptyLinesIntoAccount(): void
    {
        self::assertEquals(0, BlockString::getIndentation("a\n "));
        self::assertEquals(0, BlockString::getIndentation("a\n\t"));
        self::assertEquals(1, BlockString::getIndentation("a\n\n b"));
        self::assertEquals(2, BlockString::getIndentation("a\n \n  b"));
    }
}
