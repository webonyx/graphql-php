<?php

declare(strict_types=1);

namespace GraphQL\Tests\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Language\Token;
use GraphQL\Tests\PHPUnit\ArraySubsetAsserts;
use GraphQL\Utils\Utils;
use PHPUnit\Framework\TestCase;
use function count;
use function json_decode;

class LexerTest extends TestCase
{
    use ArraySubsetAsserts;

    /**
     * @see it('disallows uncommon control characters')
     */
    public function testDissallowsUncommonControlCharacters() : void
    {
        $this->expectSyntaxError(
            Utils::chr(0x0007),
            'Cannot contain the invalid character "\u0007"',
            $this->loc(1, 1)
        );
    }

    private function expectSyntaxError($text, $message, $location)
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage($message);
        try {
            $this->lexOne($text);
        } catch (SyntaxError $error) {
            self::assertEquals([$location], $error->getLocations());
            throw $error;
        }
    }

    /**
     * @param string $body
     *
     * @return Token
     */
    private function lexOne($body)
    {
        $lexer = new Lexer(new Source($body));

        return $lexer->advance();
    }

    private function loc($line, $column)
    {
        return new SourceLocation($line, $column);
    }

    /**
     * @see it('accepts BOM header')
     */
    public function testAcceptsBomHeader() : void
    {
        $bom      = Utils::chr(0xFEFF);
        $expected = [
            'kind'  => Token::NAME,
            'start' => 2,
            'end'   => 5,
            'value' => 'foo',
        ];

        self::assertArraySubset($expected, (array) $this->lexOne($bom . ' foo'));
    }

    /**
     * @see it('records line and column')
     */
    public function testRecordsLineAndColumn() : void
    {
        $expected = [
            'kind'   => Token::NAME,
            'start'  => 8,
            'end'    => 11,
            'line'   => 4,
            'column' => 3,
            'value'  => 'foo',
        ];
        self::assertArraySubset($expected, (array) $this->lexOne("\n \r\n \r  foo\n"));
    }

    /**
     * @see it('skips whitespace and comments')
     */
    public function testSkipsWhitespacesAndComments() : void
    {
        $example1 = '

    foo


';
        $expected = [
            'kind'  => Token::NAME,
            'start' => 6,
            'end'   => 9,
            'value' => 'foo',
        ];
        self::assertArraySubset($expected, (array) $this->lexOne($example1));

        $example2 = '
    #comment
    foo#comment
';

        $expected = [
            'kind'  => Token::NAME,
            'start' => 18,
            'end'   => 21,
            'value' => 'foo',
        ];
        self::assertArraySubset($expected, (array) $this->lexOne($example2));

        $expected = [
            'kind'  => Token::NAME,
            'start' => 3,
            'end'   => 6,
            'value' => 'foo',
        ];

        $example3 = ',,,foo,,,';
        self::assertArraySubset($expected, (array) $this->lexOne($example3));
    }

    /**
     * @see it('errors respect whitespace')
     */
    public function testErrorsRespectWhitespace() : void
    {
        $str = '' .
            "\n" .
            "\n" .
            "    ?\n" .
            "\n";

        try {
            $this->lexOne($str);
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            self::assertEquals(
                'Syntax Error: Cannot parse the unexpected character "?".' . "\n" .
                "\n" .
                "GraphQL request (3:5)\n" .
                "2: \n" .
                "3:     ?\n" .
                "       ^\n" .
                "4: \n",
                (string) $error
            );
        }
    }

    /**
     * @see it('updates line numbers in error for file context')
     */
    public function testUpdatesLineNumbersInErrorForFileContext() : void
    {
        $str    = '' .
            "\n" .
            "\n" .
            "     ?\n" .
            "\n";
        $source = new Source($str, 'foo.js', new SourceLocation(11, 12));

        try {
            $lexer = new Lexer($source);
            $lexer->advance();
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            self::assertEquals(
                'Syntax Error: Cannot parse the unexpected character "?".' . "\n" .
                "\n" .
                "foo.js (13:6)\n" .
                "12: \n" .
                "13:      ?\n" .
                "         ^\n" .
                "14: \n",
                (string) $error
            );
        }
    }

    public function testUpdatesColumnNumbersInErrorForFileContext() : void
    {
        $source = new Source('?', 'foo.js', new SourceLocation(1, 5));

        try {
            $lexer = new Lexer($source);
            $lexer->advance();
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            self::assertEquals(
                'Syntax Error: Cannot parse the unexpected character "?".' . "\n" .
                "\n" .
                "foo.js (1:5)\n" .
                '1:     ?' . "\n" .
                '       ^' . "\n",
                (string) $error
            );
        }
    }

    /**
     * @see it('lexes strings')
     */
    public function testLexesStrings() : void
    {
        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 8,
                'value' => 'simple',
            ],
            (array) $this->lexOne('"simple"')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 15,
                'value' => ' white space ',
            ],
            (array) $this->lexOne('" white space "')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 10,
                'value' => 'quote "',
            ],
            (array) $this->lexOne('"quote \\""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 25,
                'value' => 'escaped \n\r\b\t\f',
            ],
            (array) $this->lexOne('"escaped \\\\n\\\\r\\\\b\\\\t\\\\f"')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 16,
                'value' => 'slashes \\ \/',
            ],
            (array) $this->lexOne('"slashes \\\\ \\\\/"')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 13,
                'value' => 'unicode яуц',
            ],
            (array) $this->lexOne('"unicode яуц"')
        );

        $unicode = json_decode('"\u1234\u5678\u90AB\uCDEF"');
        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 34,
                'value' => 'unicode ' . $unicode,
            ],
            (array) $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::STRING,
                'start' => 0,
                'end'   => 26,
                'value' => $unicode,
            ],
            (array) $this->lexOne('"\u1234\u5678\u90AB\uCDEF"')
        );

        self::assertArraySubset(
            [
                'kind' => Token::STRING,
                'start' => 0,
                'end' => 41,
                'value' => '𝕌𝕋𝔽-16',
            ],
            (array) $this->lexOne('"\ud835\udd4C\ud835\udd4B\ud835\udd3d-16"')
        );
    }

    /**
     * @see it('lexes block strings')
     */
    public function testLexesBlockString() : void
    {
        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 12,
                'value' => 'simple',
            ],
            (array) $this->lexOne('"""simple"""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 19,
                'value' => ' white space ',
            ],
            (array) $this->lexOne('""" white space """')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 22,
                'value' => 'contains " quote',
            ],
            (array) $this->lexOne('"""contains " quote"""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 31,
                'value' => 'contains """ triplequote',
            ],
            (array) $this->lexOne('"""contains \\""" triplequote"""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 16,
                'value' => "multi\nline",
            ],
            (array) $this->lexOne("\"\"\"multi\nline\"\"\"")
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 28,
                'value' => "multi\nline\nnormalized",
            ],
            (array) $this->lexOne("\"\"\"multi\rline\r\nnormalized\"\"\"")
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 32,
                'value' => 'unescaped \\n\\r\\b\\t\\f\\u1234',
            ],
            (array) $this->lexOne('"""unescaped \\n\\r\\b\\t\\f\\u1234"""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 19,
                'value' => 'slashes \\\\ \\/',
            ],
            (array) $this->lexOne('"""slashes \\\\ \\/"""')
        );

        self::assertArraySubset(
            [
                'kind'  => Token::BLOCK_STRING,
                'start' => 0,
                'end'   => 68,
                'value' => "spans\n  multiple\n    lines",
            ],
            (array) $this->lexOne('"""

        spans
          multiple
            lines

        """')
        );
    }

    public function reportsUsefulStringErrors()
    {
        return [
            ['"', 'Unterminated string.', $this->loc(1, 2)],
            ['"no end quote', 'Unterminated string.', $this->loc(1, 14)],
            [
                "'single quotes'",
                "Unexpected single quote character ('), did you mean to use a double quote (\")?",
                $this->loc(
                    1,
                    1
                ),
            ],
            [
                '"contains unescaped \u0007 control char"',
                "Invalid character within String: \"\\u0007\"",
                $this->loc(
                    1,
                    21
                ),
            ],
            ['"null-byte is not \u0000 end of file"', 'Invalid character within String: "\\u0000"', $this->loc(1, 19)],
            ['"multi' . "\n" . 'line"', 'Unterminated string.', $this->loc(1, 7)],
            ['"multi' . "\r" . 'line"', 'Unterminated string.', $this->loc(1, 7)],
            ['"bad \\z esc"', 'Invalid character escape sequence: \\z', $this->loc(1, 7)],
            ['"bad \\x esc"', "Invalid character escape sequence: \\x", $this->loc(1, 7)],
            ['"bad \\u1 esc"', "Invalid character escape sequence: \\u1 es", $this->loc(1, 7)],
            ['"bad \\u0XX1 esc"', "Invalid character escape sequence: \\u0XX1", $this->loc(1, 7)],
            ['"bad \\uXXXX esc"', "Invalid character escape sequence: \\uXXXX", $this->loc(1, 7)],
            ['"bad \\uFXXX esc"', "Invalid character escape sequence: \\uFXXX", $this->loc(1, 7)],
            ['"bad \\uXXXF esc"', "Invalid character escape sequence: \\uXXXF", $this->loc(1, 7)],
            ['"bad \\uD835"', 'Invalid UTF-16 trailing surrogate: ', $this->loc(1, 13)],
            ['"bad \\uD835\\u1"', "Invalid UTF-16 trailing surrogate: \\u1", $this->loc(1, 13)],
            ['"bad \\uD835\\u1 esc"', "Invalid UTF-16 trailing surrogate: \\u1 es", $this->loc(1, 13)],
            ['"bad \\uD835uuFFFF esc"', 'Invalid UTF-16 trailing surrogate: uuFFFF', $this->loc(1, 13)],
            ['"bad \\uD835\\u0XX1 esc"', "Invalid UTF-16 trailing surrogate: \\u0XX1", $this->loc(1, 13)],
            ['"bad \\uD835\\uXXXX esc"', "Invalid UTF-16 trailing surrogate: \\uXXXX", $this->loc(1, 13)],
            ['"bad \\uD835\\uFXXX esc"', "Invalid UTF-16 trailing surrogate: \\uFXXX", $this->loc(1, 13)],
            ['"bad \\uD835\\uXXXF esc"', "Invalid UTF-16 trailing surrogate: \\uXXXF", $this->loc(1, 13)],
        ];
    }

    /**
     * @see          it('lex reports useful string errors')
     *
     * @dataProvider reportsUsefulStringErrors
     */
    public function testLexReportsUsefulStringErrors($str, $expectedMessage, $location) : void
    {
        $this->expectSyntaxError($str, $expectedMessage, $location);
    }

    public function reportsUsefulBlockStringErrors()
    {
        return [
            ['"""', 'Unterminated string.', $this->loc(1, 4)],
            ['"""no end quote', 'Unterminated string.', $this->loc(1, 16)],
            [
                '"""contains unescaped ' . json_decode('"\u0007"') . ' control char"""',
                "Invalid character within String: \"\\u0007\"",
                $this->loc(
                    1,
                    23
                ),
            ],
            [
                '"""null-byte is not ' . json_decode('"\u0000"') . ' end of file"""',
                "Invalid character within String: \"\\u0000\"",
                $this->loc(
                    1,
                    21
                ),
            ],
        ];
    }

    /**
     * @see          it('lex reports useful block string errors')
     *
     * @dataProvider reportsUsefulBlockStringErrors
     */
    public function testReportsUsefulBlockStringErrors($str, $expectedMessage, $location) : void
    {
        $this->expectSyntaxError($str, $expectedMessage, $location);
    }

    /**
     * @see it('lexes numbers')
     */
    public function testLexesNumbers() : void
    {
        self::assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '4'],
            (array) $this->lexOne('4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '4.123'],
            (array) $this->lexOne('4.123')
        );
        self::assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 2, 'value' => '-4'],
            (array) $this->lexOne('-4')
        );
        self::assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '9'],
            (array) $this->lexOne('9')
        );
        self::assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '0'],
            (array) $this->lexOne('0')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '-4.123'],
            (array) $this->lexOne('-4.123')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '0.123'],
            (array) $this->lexOne('0.123')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123e4'],
            (array) $this->lexOne('123e4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123E4'],
            (array) $this->lexOne('123E4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e-4'],
            (array) $this->lexOne('123e-4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e+4'],
            (array) $this->lexOne('123e+4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123e4'],
            (array) $this->lexOne('-1.123e4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123E4'],
            (array) $this->lexOne('-1.123E4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e-4'],
            (array) $this->lexOne('-1.123e-4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e+4'],
            (array) $this->lexOne('-1.123e+4')
        );
        self::assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 11, 'value' => '-1.123e4567'],
            (array) $this->lexOne('-1.123e4567')
        );
    }

    public function reportsUsefulNumberErrors()
    {
        return [
            ['00', 'Invalid number, unexpected digit after 0: "0"', $this->loc(1, 2)],
            ['+1', 'Cannot parse the unexpected character "+".', $this->loc(1, 1)],
            ['1.', 'Invalid number, expected digit but got: <EOF>', $this->loc(1, 3)],
            ['1.e1', 'Invalid number, expected digit but got: "e"', $this->loc(1, 3)],
            ['.123', 'Cannot parse the unexpected character ".".', $this->loc(1, 1)],
            ['1.A', 'Invalid number, expected digit but got: "A"', $this->loc(1, 3)],
            ['-A', 'Invalid number, expected digit but got: "A"', $this->loc(1, 2)],
            ['1.0e', 'Invalid number, expected digit but got: <EOF>', $this->loc(1, 5)],
            ['1.0eA', 'Invalid number, expected digit but got: "A"', $this->loc(1, 5)],
        ];
    }

    /**
     * @see          it('lex reports useful number errors')
     *
     * @dataProvider reportsUsefulNumberErrors
     */
    public function testReportsUsefulNumberErrors($str, $expectedMessage, $location) : void
    {
        $this->expectSyntaxError($str, $expectedMessage, $location);
    }

    /**
     * @see it('lexes punctuation')
     */
    public function testLexesPunctuation() : void
    {
        self::assertArraySubset(
            ['kind' => Token::BANG, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('!')
        );
        self::assertArraySubset(
            ['kind' => Token::DOLLAR, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('$')
        );
        self::assertArraySubset(
            ['kind' => Token::PAREN_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('(')
        );
        self::assertArraySubset(
            ['kind' => Token::PAREN_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(')')
        );
        self::assertArraySubset(
            ['kind' => Token::SPREAD, 'start' => 0, 'end' => 3, 'value' => null],
            (array) $this->lexOne('...')
        );
        self::assertArraySubset(
            ['kind' => Token::COLON, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(':')
        );
        self::assertArraySubset(
            ['kind' => Token::EQUALS, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('=')
        );
        self::assertArraySubset(
            ['kind' => Token::AT, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('@')
        );
        self::assertArraySubset(
            ['kind' => Token::BRACKET_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('[')
        );
        self::assertArraySubset(
            ['kind' => Token::BRACKET_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(']')
        );
        self::assertArraySubset(
            ['kind' => Token::BRACE_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('{')
        );
        self::assertArraySubset(
            ['kind' => Token::PIPE, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('|')
        );
        self::assertArraySubset(
            ['kind' => Token::BRACE_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('}')
        );
    }

    public function reportsUsefulUnknownCharErrors()
    {
        $unicode1 = json_decode('"\u203B"');
        $unicode2 = json_decode('"\u200b"');

        return [
            ['..', 'Cannot parse the unexpected character ".".', $this->loc(1, 1)],
            ['?', 'Cannot parse the unexpected character "?".', $this->loc(1, 1)],
            [$unicode1, "Cannot parse the unexpected character \"\\u203b\".", $this->loc(1, 1)],
            [$unicode2, "Cannot parse the unexpected character \"\\u200b\".", $this->loc(1, 1)],
        ];
    }

    /**
     * @see          it('lex reports useful unknown character error')
     *
     * @dataProvider reportsUsefulUnknownCharErrors
     */
    public function testReportsUsefulUnknownCharErrors($str, $expectedMessage, $location) : void
    {
        $this->expectSyntaxError($str, $expectedMessage, $location);
    }

    /**
     * @see it('lex reports useful information for dashes in names')
     */
    public function testReportsUsefulDashesInfo() : void
    {
        $q     = 'a-b';
        $lexer = new Lexer(new Source($q));
        self::assertArraySubset(
            ['kind' => Token::NAME, 'start' => 0, 'end' => 1, 'value' => 'a'],
            (array) $lexer->advance()
        );

        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessage('Syntax Error: Invalid number, expected digit but got: "b"');
        try {
            $lexer->advance();
            self::fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            self::assertEquals([$this->loc(1, 3)], $error->getLocations());
            throw $error;
        }
    }

    /**
     * @see it('produces double linked list of tokens, including comments')
     */
    public function testDoubleLinkedList() : void
    {
        $lexer = new Lexer(new Source('{
      #comment
      field
    }'));

        $startToken = $lexer->token;
        do {
            $endToken = $lexer->advance();
            // Lexer advances over ignored comment tokens to make writing parsers
            // easier, but will include them in the linked list result.
            self::assertNotEquals('Comment', $endToken->kind);
        } while ($endToken->kind !== '<EOF>');

        self::assertEquals(null, $startToken->prev);
        self::assertEquals(null, $endToken->next);

        $tokens = [];
        for ($tok = $startToken; $tok; $tok = $tok->next) {
            if (count($tokens) > 0) {
                // Tokens are double-linked, prev should point to last seen token.
                self::assertSame($tokens[count($tokens) - 1], $tok->prev);
            }
            $tokens[] = $tok;
        }

        self::assertEquals(
            [
                '<SOF>',
                '{',
                'Comment',
                'Name',
                '}',
                '<EOF>',
            ],
            Utils::map(
                $tokens,
                static function ($tok) {
                    return $tok->kind;
                }
            )
        );
    }
}
