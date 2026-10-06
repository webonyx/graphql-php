<?php declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\FormattedError;
use GraphQL\Error\SyntaxError;
use GraphQL\Language\Lexer;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use PHPUnit\Framework\TestCase;

use function Safe\file_get_contents;

/**
 * Hand-picked cases, generated ones are in StripIgnoredCharactersFuzzTest.
 *
 * @see describe('stripIgnoredCharacters', () => {
 */
final class StripIgnoredCharactersTest extends TestCase
{
    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function lexValue(string $str): ?string
    {
        $lexer = new Lexer(new Source($str));
        $value = $lexer->advance()->value;

        self::assertSame(Token::EOF, $lexer->advance()->kind, 'Expected EOF');

        return $value;
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function assertStripped(string $expected, string $docString): void
    {
        $stripped = Printer::stripIgnoredCharacters($docString);
        self::assertSame($expected, $stripped);

        $strippedTwice = Printer::stripIgnoredCharacters($stripped);
        self::assertSame($expected, $strippedTwice);
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function assertStaysTheSame(string $docString): void
    {
        self::assertStripped($docString, $docString);
    }

    /**
     * @throws \JsonException
     * @throws SyntaxError
     */
    private static function assertStrippedString(string $expected, string $blockStr): void
    {
        $originalValue = self::lexValue($blockStr);
        $strippedValue = self::lexValue(Printer::stripIgnoredCharacters($blockStr));
        self::assertSame($originalValue, $strippedValue);

        self::assertStripped($expected, $blockStr);
    }

    /** @see it('strips ignored characters from GraphQL query document', () => { */
    public function testStripsIgnoredCharactersFromGraphQLQueryDocument(): void
    {
        $query = <<<'GRAPHQL'
        query SomeQuery($foo: String!, $bar: String) {
          someField(foo: $foo, bar: $bar) {
            a
            b {
              c
              d
            }
          }
        }
        GRAPHQL;

        self::assertSame(
            'query SomeQuery($foo:String!$bar:String){someField(foo:$foo bar:$bar){a b{c d}}}',
            Printer::stripIgnoredCharacters($query)
        );
    }

    /** @see it('accepts Source object', () => { */
    public function testAcceptsSourceObject(): void
    {
        self::assertSame('{a}', Printer::stripIgnoredCharacters(new Source('{ a }')));
    }

    /** @see it('strips ignored characters from GraphQL SDL document', () => { */
    public function testStripsIgnoredCharactersFromGraphQLSDLDocument(): void
    {
        $sdl = <<<'GRAPHQL'
        """
        Type description
        """
        type Foo {
          """
          Field description
          """
          bar: String
        }
        GRAPHQL;

        self::assertSame(
            '"""Type description""" type Foo{"""Field description""" bar:String}',
            Printer::stripIgnoredCharacters($sdl)
        );
    }

    /** @see it('report document with invalid token', () => { */
    public function testReportDocumentWithInvalidToken(): void
    {
        try {
            Printer::stripIgnoredCharacters("{ foo(arg: \"\n\"");
            self::fail('Expected SyntaxError');
        } catch (SyntaxError $error) {
            self::assertSame(
                <<<'EOF'
                Syntax Error: Unterminated string.

                GraphQL request (1:13)
                1: { foo(arg: "
                               ^
                2: "

                EOF,
                FormattedError::printError($error)
            );
        }
    }

    /** @see it('strips non-parsable document', () => { */
    public function testStripsNonParsableDocument(): void
    {
        self::assertStripped('{foo(arg:"str"', '{ foo(arg: "str"');
    }

    /** @see it('strips documents with only ignored characters', () => { */
    public function testStripsDocumentsWithOnlyIgnoredCharacters(): void
    {
        self::assertStripped('', "\n");
        self::assertStripped('', ',');
        self::assertStripped('', ',,');
        self::assertStripped('', "#comment\n, \n");
    }

    /** @see it('strips leading and trailing ignored tokens', () => { */
    public function testStripsLeadingAndTrailingIgnoredTokens(): void
    {
        self::assertStripped('1', "\n1");
        self::assertStripped('1', ',1');
        self::assertStripped('1', ',,1');
        self::assertStripped('1', "#comment\n, \n1");

        self::assertStripped('1', "1\n");
        self::assertStripped('1', '1,');
        self::assertStripped('1', '1,,');
        self::assertStripped('1', "1#comment\n, \n");
    }

    /** @see it('strips ignored tokens between punctuator tokens', () => { */
    public function testStripsIgnoredTokensBetweenPunctuatorTokens(): void
    {
        self::assertStripped('[)', '[,)');
        self::assertStripped('[)', "[\r)");
        self::assertStripped('[)', "[\r\r)");
        self::assertStripped('[)', "[\r,)");
        self::assertStripped('[)', "[,\n)");
    }

    /** @see it('strips ignored tokens between punctuator and non-punctuator tokens', () => { */
    public function testStripsIgnoredTokensBetweenPunctuatorAndNonPunctuatorTokens(): void
    {
        self::assertStripped('[1', '[,1');
        self::assertStripped('[1', "[\r1");
        self::assertStripped('[1', "[\r\r1");
        self::assertStripped('[1', "[\r,1");
        self::assertStripped('[1', "[,\n1");
    }

    /** @see it('strips ignored tokens between non-punctuator and punctuator tokens', () => { */
    public function testStripsIgnoredTokensBetweenNonPunctuatorAndPunctuatorTokens(): void
    {
        self::assertStripped('1[', '1,[');
        self::assertStripped('1[', "1\r[");
        self::assertStripped('1[', "1\r\r[");
        self::assertStripped('1[', "1\r,[");
        self::assertStripped('1[', "1,\n[");
    }

    /** @see it('replace ignored tokens between non-punctuator tokens and spread with space', () => { */
    public function testReplaceIgnoredTokensBetweenNonPunctuatorTokensAndSpreadWithSpace(): void
    {
        self::assertStripped('a ...', 'a ...');
        self::assertStripped('1 ...', '1 ...');
        self::assertStripped('1 ......', '1 ... ...');
    }

    /** @see it('replace ignored tokens between non-punctuator tokens with space', () => { */
    public function testReplaceIgnoredTokensBetweenNonPunctuatorTokensWithSpace(): void
    {
        self::assertStaysTheSame('1 2');
        self::assertStaysTheSame('"" ""');
        self::assertStaysTheSame('a b');

        self::assertStripped('a 1', 'a,1');
        self::assertStripped('a 1', 'a,,1');
        self::assertStripped('a 1', 'a  1');
        self::assertStripped('a 1', "a \t 1");
    }

    /** @see it('does not strip ignored tokens embedded in the string', () => { */
    public function testDoesNotStripIgnoredTokensEmbeddedInTheString(): void
    {
        self::assertStaysTheSame('" "');
        self::assertStaysTheSame('","');
        self::assertStaysTheSame('",,"');
        self::assertStaysTheSame('",|"');
    }

    /** @see it('does not strip ignored tokens embedded in the block string', () => { */
    public function testDoesNotStripIgnoredTokensEmbeddedInTheBlockString(): void
    {
        self::assertStaysTheSame('""","""');
        self::assertStaysTheSame('""",,"""');
        self::assertStaysTheSame('""",|"""');
    }

    /** @see it('strips ignored characters inside block strings', () => { */
    public function testStripsIgnoredCharactersInsideBlockStrings(): void
    {
        self::assertStrippedString('""""""', '""""""');
        self::assertStrippedString('""""""', '""" """');

        self::assertStrippedString('"""a"""', '"""a"""');
        self::assertStrippedString('""" a"""', '""" a"""');
        self::assertStrippedString('""" a """', '""" a """');

        self::assertStrippedString('""""""', "\"\"\"\n\"\"\"");
        self::assertStrippedString("\"\"\"a\nb\"\"\"", "\"\"\"a\nb\"\"\"");
        self::assertStrippedString("\"\"\"a\nb\"\"\"", "\"\"\"a\rb\"\"\"");
        self::assertStrippedString("\"\"\"a\nb\"\"\"", "\"\"\"a\r\nb\"\"\"");
        self::assertStrippedString("\"\"\"a\n\nb\"\"\"", "\"\"\"a\r\n\nb\"\"\"");

        self::assertStrippedString("\"\"\"\\\n\"\"\"", "\"\"\"\\\n\"\"\"");
        self::assertStrippedString("\"\"\"\"\n\"\"\"", "\"\"\"\"\n\"\"\"");
        self::assertStrippedString('"""\\""""""', "\"\"\"\\\"\"\"\n\"\"\"");

        self::assertStrippedString("\"\"\"\na\n b\"\"\"", "\"\"\"\na\n b\"\"\"");
        self::assertStrippedString("\"\"\"a\nb\"\"\"", "\"\"\"\n a\n b\"\"\"");
        self::assertStrippedString("\"\"\"a\n b\nc\"\"\"", "\"\"\"\na\n b\nc\"\"\"");
    }

    public function testStripsNonASCIICharactersInStrings(): void
    {
        self::assertStripped('{a(b:"ä ö" c:""" ü """)}', '{ a(b: "ä ö", c: """ ü """) }');
        self::assertStripped('{a(b:"😀€" c:"x")}', '{ a(b: "😀€", c: "x") }');
        self::assertStripped('{a(b:"😀x" c:"€y")}', "# €😀\n{ a(b: \"😀x\", c: \"€y\") }");
        self::assertStripped('"ö" "ä"', "\u{FEFF}# ü\n\"ö\" \"ä\"");
    }

    public function testRejectsInvalidUTF8(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Invalid UTF-8 byte: 0xFF');
        Printer::stripIgnoredCharacters("{ a(b: \"\xFFabc\") }");
    }

    /** @see it('strips kitchen sink query but maintains the exact same AST', () => { */
    public function testStripsKitchenSinkQueryButMaintainsTheExactSameAST(): void
    {
        $kitchenSinkQuery = file_get_contents(__DIR__ . '/kitchen-sink.graphql');

        $strippedQuery = Printer::stripIgnoredCharacters($kitchenSinkQuery);
        self::assertSame($strippedQuery, Printer::stripIgnoredCharacters($strippedQuery));

        $queryAST = Parser::parse($kitchenSinkQuery, ['noLocation' => true]);
        $strippedAST = Parser::parse($strippedQuery, ['noLocation' => true]);
        self::assertEquals($queryAST, $strippedAST);
    }

    /** @see it('strips kitchen sink SDL but maintains the exact same AST', () => { */
    public function testStripsKitchenSinkSDLButMaintainsTheExactSameAST(): void
    {
        $kitchenSinkSDL = file_get_contents(__DIR__ . '/schema-kitchen-sink.graphql');

        $strippedSDL = Printer::stripIgnoredCharacters($kitchenSinkSDL);
        self::assertSame($strippedSDL, Printer::stripIgnoredCharacters($strippedSDL));

        $sdlAST = Parser::parse($kitchenSinkSDL, ['noLocation' => true]);
        $strippedAST = Parser::parse($strippedSDL, ['noLocation' => true]);
        self::assertEquals($sdlAST, $strippedAST);
    }
}
