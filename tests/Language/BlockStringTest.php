<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Language\BlockString;
use PHPUnit\Framework\TestCase;

final class BlockStringTest extends TestCase
{
    private static function joinLines(string ...$args): string
    {
        return implode("\n", $args);
    }

    /**
     * @see describe('dedentBlockStringLines', () => {
     * @see it('handles empty string', () => {
     */
    public function testHandlesEmptyString(): void
    {
        self::assertSame(
            '',
            BlockString::dedentBlockStringLines('')
        );
    }

    /** @see it('does not dedent first line', () => { */
    public function testDoesNotDedentFirstLine(): void
    {
        self::assertSame(
            '  a',
            BlockString::dedentBlockStringLines('  a')
        );
        self::assertSame(
            <<<'GRAPHQL'
             a
            b
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
             a
              b
            GRAPHQL
            )
        );
    }

    /** @see it('removes minimal indentation length', () => { */
    public function testRemovesMinimalIndentationLength(): void
    {
        self::assertSame(
            <<<'GRAPHQL'
            a
             b
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            
             a
              b
            GRAPHQL
            )
        );
        self::assertSame(
            <<<'GRAPHQL'
             a
            b
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            
              a
             b
            GRAPHQL
            )
        );
        self::assertSame(
            <<<'GRAPHQL'
              a
             b
            c
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            
              a
             b
            c
            GRAPHQL
            )
        );
    }

    /** @see it('dedent both tab and space as single character', () => { */
    public function testDedentBothTabAndSpaceAsSingleCharacter(): void
    {
        self::assertSame(
            <<<GRAPHQL
            a
                     b
            GRAPHQL,
            BlockString::dedentBlockStringLines("\n\ta\n          b")
        );
        self::assertSame(
            <<<GRAPHQL
            a
                    b
            GRAPHQL,
            BlockString::dedentBlockStringLines("\n\t a\n          b")
        );
        self::assertSame(
            <<<GRAPHQL
            a
                   b
            GRAPHQL,
            BlockString::dedentBlockStringLines("\n \t a\n          b")
        );
    }

    /** @see it('dedent do not take empty lines into account', () => { */
    public function testDedentDoNotTakeEmptyLinesIntoAccount(): void
    {
        self::assertSame(
            <<<'GRAPHQL'
            a
            
            b
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            a
            
             b
            GRAPHQL
            )
        );
        self::assertSame(
            <<<'GRAPHQL'
            a
            
            b
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            a
            
              b
            GRAPHQL
            )
        );
    }

    /** @see it('removes uniform indentation from a string') */
    public function testRemovesUniformIndentationFromAString(): void
    {
        self::assertSame(
            <<<'GRAPHQL'
            Hello,
              World!
            
            Yours,
              GraphQL.
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            
                Hello,
                  World!
            
                Yours,
                  GraphQL.
            GRAPHQL
            )
        );
    }

    /** @see it('removes empty leading and trailing lines', () => { */
    public function testRemovesEmptyLeadingAndTrailingLines(): void
    {
        self::assertSame(
            <<<'GRAPHQL'
            Hello,
              World!
            
            Yours,
              GraphQL.
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
            
            
                Hello,
                  World!
            
                Yours,
                  GraphQL.
            
            
            GRAPHQL
            )
        );
    }

    /** @see it('removes blank leading and trailing lines', () => { */
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
        self::assertSame(
            self::joinLines('Hello,', '  World!', '', 'Yours,', '  GraphQL.'),
            BlockString::dedentBlockStringLines($rawValue)
        );
    }

    /** @see it('retains indentation from first line', () => { */
    public function testRetainsIndentationFromFirstLine(): void
    {
        self::assertSame(
            <<<'GRAPHQL'
                Hello,
              World!
            
            Yours,
              GraphQL.
            GRAPHQL,
            BlockString::dedentBlockStringLines(
                <<<'GRAPHQL'
                Hello,
                  World!
            
                Yours,
                  GraphQL.
              
                
            GRAPHQL
            )
        );
    }

    /** @see it('does not alter trailing spaces') */
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
        self::assertSame(
            self::joinLines(
                'Hello,     ',
                '  World!   ',
                '           ',
                'Yours,     ',
                '  GraphQL. ',
            ),
            BlockString::dedentBlockStringLines($rawValue)
        );
    }

    // describe('getBlockStringIndentation')

    /** @see it('returns zero for an empty string') */
    public function testReturnsZeroForAnEmptyString(): void
    {
        self::assertSame(0, BlockString::getIndentation(''));
    }

    /** @see it('do not take first line into account') */
    public function testDoNotTakeFirstLineIntoAccount(): void
    {
        self::assertSame(0, BlockString::getIndentation('  a'));
        self::assertSame(2, BlockString::getIndentation(" a\n  b"));
    }

    /** @see it('returns minimal indentation length') */
    public function testReturnsMinimalIndentationLength(): void
    {
        self::assertSame(1, BlockString::getIndentation("\n a\n  b"));
        self::assertSame(1, BlockString::getIndentation("\n  a\n b"));
        self::assertSame(0, BlockString::getIndentation("\n  a\n b\nc"));
    }

    /** @see it('count both tab and space as single character') */
    public function testCountBothTabAndSpaceAsSingleCharacter(): void
    {
        self::assertSame(1, BlockString::getIndentation("\n\ta\n          b"));
        self::assertSame(2, BlockString::getIndentation("\n\t a\n          b"));
        self::assertSame(3, BlockString::getIndentation("\n \t a\n          b"));
    }

    /** @see it('do not take empty lines into account') */
    public function testDoNotTakeEmptyLinesIntoAccount(): void
    {
        self::assertSame(0, BlockString::getIndentation("a\n "));
        self::assertSame(0, BlockString::getIndentation("a\n\t"));
        self::assertSame(1, BlockString::getIndentation("a\n\n b"));
        self::assertSame(2, BlockString::getIndentation("a\n \n  b"));
    }

    /**
     * @see describe('printBlockString', () => {
     * @see it('does not escape characters', () => {
     */
    public function testDoNotEscapeCharacters(): void
    {
        $str = "\" \\ / \u{8} \f \n \r \t"; // \u{8} === \b
        self::assertSame(
            <<<EOF
            """
            {$str}
            """
            EOF,
            BlockString::print($str)
        );
    }

    /** @see it('by default print block strings as single line', () => { */
    public function testByDefaultPrintBlockStringsAsSingleLine(): void
    {
        $str = 'one liner';
        self::assertSame('"""one liner"""', BlockString::print($str));
    }

    /** @see it('by default print block strings ending with triple quotation as multi-line', () => { */
    public function testByDefaultPrintBlockStringsEndingWithTripleQuotationAsMultiLine(): void
    {
        $str = 'triple quotation """';
        self::assertSame(
            <<<EOF
            """
            triple quotation \\"""
            """
            EOF,
            BlockString::print($str)
        );
    }

    /** @see it('correctly prints single-line with leading space') */
    public function testCorrectlyPrintsSingleLineWithLeadingSpace(): void
    {
        $str = '    space-led string';
        self::assertSame('"""    space-led string"""', BlockString::print($str));
    }

    /** @see it('correctly prints single-line with leading space and trailing quotation', () => { */
    public function testCorrectlyPrintsSingleLineWithLeadingSpaceAndQuotation(): void
    {
        $str = '    space-led value "quoted string"';
        self::assertSame(
            <<<'EOF'
            """    space-led value "quoted string"
            """
            EOF,
            BlockString::print($str)
        );
    }

    /** @see it('correctly prints single-line with trailing backslash') */
    public function testCorrectlyPrintsSingleLineWithTrailingBackslash(): void
    {
        $str = 'backslash \\';
        self::assertSame(
            <<<EOF
            """
            backslash \\
            """
            EOF,
            BlockString::print($str)
        );
    }

    /** @see it('correctly prints multi-line with internal indent', () => { */
    public function testCorrectlyPrintsMultiLineWithInternalIndent(): void
    {
        $str = <<<EOF
        no indent
         with indent
        EOF;
        self::assertSame(
            <<<EOF
            """
            no indent
             with indent
            """
            EOF,
            BlockString::print($str)
        );
    }

    /** @see it('correctly prints string with a first line indentation') */
    public function testCorrectlyPrintsStringWithAFirstLineIndentation(): void
    {
        $str = <<<EOF
            first  
          line     
        indentation
             string
        EOF;
        self::assertSame(
            <<<EOF
            """
                first  
              line     
            indentation
                 string
            """
            EOF,
            BlockString::print($str)
        );
    }

    public function testCorrectlyPrintsEmptyString(): void
    {
        $str = '';
        self::assertSame('""""""', BlockString::print($str));
    }
}
