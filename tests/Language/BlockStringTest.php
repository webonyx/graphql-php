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
}
