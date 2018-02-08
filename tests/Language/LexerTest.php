<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Language\Token;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils\Utils;

class LexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it disallows uncommon control characters
     */
    public function testDissallowsUncommonControlCharacters()
    {
        $char = Utils::chr(0x0007);

        $this->setExpectedExceptionRegExp(SyntaxError::class, '/' . preg_quote('Syntax Error GraphQL (1:1) Cannot contain the invalid character "\u0007"', '/') . '/');
        $this->lexOne($char);
    }

    /**
     * @it accepts BOM header
     */
    public function testAcceptsBomHeader()
    {
        $bom = Utils::chr(0xFEFF);
        $expected = [
            'kind' => Token::NAME,
            'start' => 2,
            'end' => 5,
            'value' => 'foo'
        ];

        $this->assertArraySubset($expected, (array) $this->lexOne($bom . ' foo'));
    }

    /**
     * @it records line and column
     */
    public function testRecordsLineAndColumn()
    {
        $expected = [
            'kind' => Token::NAME,
            'start' => 8,
            'end' => 11,
            'line' => 4,
            'column' => 3,
            'value' => 'foo'
        ];
        $this->assertArraySubset($expected, (array) $this->lexOne("\n \r\n \r  foo\n"));
    }

    /**
     * @it skips whitespace and comments
     */
    public function testSkipsWhitespacesAndComments()
    {
        $example1 = '

    foo


';
        $expected = [
            'kind' => Token::NAME,
            'start' => 6,
            'end' => 9,
            'value' => 'foo'
        ];
        $this->assertArraySubset($expected, (array) $this->lexOne($example1));

        $example2 = '
    #comment
    foo#comment
';

        $expected = [
            'kind' => Token::NAME,
            'start' => 18,
            'end' => 21,
            'value' => 'foo'
        ];
        $this->assertArraySubset($expected, (array) $this->lexOne($example2));

        $expected = [
            'kind' => Token::NAME,
            'start' => 3,
            'end' => 6,
            'value' => 'foo'
        ];

        $example3 = ',,,foo,,,';
        $this->assertArraySubset($expected, (array) $this->lexOne($example3));
    }

    /**
     * @it errors respect whitespace
     */
    public function testErrorsRespectWhitespace()
    {
        $str = '' .
            "\n" .
            "\n" .
            "    ?\n" .
            "\n";

        $this->setExpectedException(SyntaxError::class,
            'Syntax Error GraphQL (3:5) Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            "2: \n" .
            "3:     ?\n" .
            "       ^\n" .
            "4: \n");
        $this->lexOne($str);
    }

    /**
     * @it updates line numbers in error for file context
     */
    public function testUpdatesLineNumbersInErrorForFileContext()
    {
        $str = '' .
            "\n" .
            "\n" .
            "     ?\n" .
            "\n";
        $source = new Source($str, 'foo.js', new SourceLocation(11, 12));

        $this->setExpectedException(
            SyntaxError::class,
            'Syntax Error foo.js (13:6) ' .
            'Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            '12: ' . "\n" .
            '13:      ?' . "\n" .
            '         ^' . "\n" .
            '14: ' . "\n"
        );
        $lexer = new Lexer($source);
        $lexer->advance();
    }

    public function testUpdatesColumnNumbersInErrorForFileContext()
    {
        $source = new Source('?', 'foo.js', new SourceLocation(1, 5));

        $this->setExpectedException(
            SyntaxError::class,
            'Syntax Error foo.js (1:5) ' .
            'Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            '1:     ?' . "\n" .
            '       ^' . "\n"
        );
        $lexer = new Lexer($source);
        $lexer->advance();
    }

    /**
     * @it lexes strings
     */
    public function testLexesStrings()
    {
        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 8,
            'value' => 'simple'
        ], (array) $this->lexOne('"simple"'));


        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 15,
            'value' => ' white space '
        ], (array) $this->lexOne('" white space "'));

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 10,
            'value' => 'quote "'
        ], (array) $this->lexOne('"quote \\""'));

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 25,
            'value' => 'escaped \n\r\b\t\f'
        ], (array) $this->lexOne('"escaped \\\\n\\\\r\\\\b\\\\t\\\\f"'));

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 16,
            'value' => 'slashes \\ \/'
        ], (array) $this->lexOne('"slashes \\\\ \\\\/"'));

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 13,
            'value' => 'unicode яуц'
        ], (array) $this->lexOne('"unicode яуц"'));

        $unicode = json_decode('"\u1234\u5678\u90AB\uCDEF"');
        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 34,
            'value' => 'unicode ' . $unicode
        ], (array) $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"'));

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 26,
            'value' => $unicode
        ], (array) $this->lexOne('"\u1234\u5678\u90AB\uCDEF"'));
    }

    /**
     * @it lexes block strings
     */
    public function testLexesBlockString()
    {
        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 12,
            'value' => 'simple'
        ], (array) $this->lexOne('"""simple"""'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 19,
            'value' => ' white space '
        ], (array) $this->lexOne('""" white space """'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 22,
            'value' => 'contains " quote'
        ], (array) $this->lexOne('"""contains " quote"""'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 31,
            'value' => 'contains """ triplequote'
        ], (array) $this->lexOne('"""contains \\""" triplequote"""'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 16,
            'value' => "multi\nline"
        ], (array) $this->lexOne("\"\"\"multi\nline\"\"\""));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 28,
            'value' => "multi\nline\nnormalized"
        ], (array) $this->lexOne("\"\"\"multi\rline\r\nnormalized\"\"\""));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 32,
            'value' => 'unescaped \\n\\r\\b\\t\\f\\u1234'
        ], (array) $this->lexOne('"""unescaped \\n\\r\\b\\t\\f\\u1234"""'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 19,
            'value' => 'slashes \\\\ \\/'
        ], (array) $this->lexOne('"""slashes \\\\ \\/"""'));

        $this->assertArraySubset([
            'kind' => Token::BLOCK_STRING,
            'start' => 0,
            'end' => 68,
            'value' => "spans\n  multiple\n    lines"
        ], (array) $this->lexOne("\"\"\"

        spans
          multiple
            lines

        \"\"\""));
    }

    public function reportsUsefulBlockStringErrors() {
        return [
            ['"""', "Syntax Error GraphQL (1:4) Unterminated string.\n\n1: \"\"\"\n      ^\n"],
            ['"""no end quote', "Syntax Error GraphQL (1:16) Unterminated string.\n\n1: \"\"\"no end quote\n                  ^\n"],
            ['"""contains unescaped ' . json_decode('"\u0007"') . ' control char"""', "Syntax Error GraphQL (1:23) Invalid character within String: \"\\u0007\""],
            ['"""null-byte is not ' . json_decode('"\u0000"') . ' end of file"""', "Syntax Error GraphQL (1:21) Invalid character within String: \"\\u0000\""],
        ];
    }

    /**
     * @dataProvider reportsUsefulBlockStringErrors
     * @it lex reports useful block string errors
     */
    public function testReportsUsefulBlockStringErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    public function reportsUsefulStringErrors() {
        return [
            ['"', "Syntax Error GraphQL (1:2) Unterminated string.\n\n1: \"\n    ^\n"],
            ['"no end quote', "Syntax Error GraphQL (1:14) Unterminated string.\n\n1: \"no end quote\n                ^\n"],
            ["'single quotes'", "Syntax Error GraphQL (1:1) Unexpected single quote character ('), did you mean to use a double quote (\")?\n\n1: 'single quotes'\n   ^\n"],
            ['"contains unescaped \u0007 control char"', "Syntax Error GraphQL (1:21) Invalid character within String: \"\\u0007\"\n\n1: \"contains unescaped \\u0007 control char\"\n                       ^\n"],
            ['"null-byte is not \u0000 end of file"', 'Syntax Error GraphQL (1:19) Invalid character within String: "\\u0000"' . "\n\n1: \"null-byte is not \\u0000 end of file\"\n                     ^\n"],
            ['"multi' . "\n" . 'line"', "Syntax Error GraphQL (1:7) Unterminated string.\n\n1: \"multi\n         ^\n2: line\"\n"],
            ['"multi' . "\r" . 'line"', "Syntax Error GraphQL (1:7) Unterminated string.\n\n1: \"multi\n         ^\n2: line\"\n"],
            ['"bad \\z esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\z\n\n1: \"bad \\z esc\"\n         ^\n"],
            ['"bad \\x esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\x\n\n1: \"bad \\x esc\"\n         ^\n"],
            ['"bad \\u1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u1 es\n\n1: \"bad \\u1 esc\"\n         ^\n"],
            ['"bad \\u0XX1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u0XX1\n\n1: \"bad \\u0XX1 esc\"\n         ^\n"],
            ['"bad \\uXXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXX\n\n1: \"bad \\uXXXX esc\"\n         ^\n"],
            ['"bad \\uFXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uFXXX\n\n1: \"bad \\uFXXX esc\"\n         ^\n"],
            ['"bad \\uXXXF esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXF\n\n1: \"bad \\uXXXF esc\"\n         ^\n"],
        ];
    }

    /**
     * @dataProvider reportsUsefulStringErrors
     * @it lex reports useful string errors
     */
    public function testLexReportsUsefulStringErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lexes numbers
     */
    public function testLexesNumbers()
    {
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '4'],
            (array) $this->lexOne('4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '4.123'],
            (array) $this->lexOne('4.123')
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 2, 'value' => '-4'],
            (array) $this->lexOne('-4')
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '9'],
            (array) $this->lexOne('9')
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '0'],
            (array) $this->lexOne('0')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '-4.123'],
            (array) $this->lexOne('-4.123')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '0.123'],
            (array) $this->lexOne('0.123')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123e4'],
            (array) $this->lexOne('123e4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123E4'],
            (array) $this->lexOne('123E4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e-4'],
            (array) $this->lexOne('123e-4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e+4'],
            (array) $this->lexOne('123e+4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123e4'],
            (array) $this->lexOne('-1.123e4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123E4'],
            (array) $this->lexOne('-1.123E4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e-4'],
            (array) $this->lexOne('-1.123e-4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e+4'],
            (array) $this->lexOne('-1.123e+4')
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 11, 'value' => '-1.123e4567'],
            (array) $this->lexOne('-1.123e4567')
        );
    }

    public function reportsUsefulNumberErrors()
    {
        return [
            [ '00', "Syntax Error GraphQL (1:2) Invalid number, unexpected digit after 0: \"0\"\n\n1: 00\n    ^\n"],
            [ '+1', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"+\".\n\n1: +1\n   ^\n"],
            [ '1.', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: <EOF>\n\n1: 1.\n     ^\n"],
            [ '.123', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \".\".\n\n1: .123\n   ^\n"],
            [ '1.A', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: \"A\"\n\n1: 1.A\n     ^\n"],
            [ '-A', "Syntax Error GraphQL (1:2) Invalid number, expected digit but got: \"A\"\n\n1: -A\n    ^\n"],
            [ '1.0e', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: <EOF>\n\n1: 1.0e\n       ^\n"],
            [ '1.0eA', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: \"A\"\n\n1: 1.0eA\n       ^\n"],
        ];
    }

    /**
     * @dataProvider reportsUsefulNumberErrors
     * @it lex reports useful number errors
     */
    public function testReportsUsefulNumberErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lexes punctuation
     */
    public function testLexesPunctuation()
    {
        $this->assertArraySubset(
            ['kind' => Token::BANG, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('!')
        );
        $this->assertArraySubset(
            ['kind' => Token::DOLLAR, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('$')
        );
        $this->assertArraySubset(
            ['kind' => Token::PAREN_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('(')
        );
        $this->assertArraySubset(
            ['kind' => Token::PAREN_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(')')
        );
        $this->assertArraySubset(
            ['kind' => Token::SPREAD, 'start' => 0, 'end' => 3, 'value' => null],
            (array) $this->lexOne('...')
        );
        $this->assertArraySubset(
            ['kind' => Token::COLON, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(':')
        );
        $this->assertArraySubset(
            ['kind' => Token::EQUALS, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('=')
        );
        $this->assertArraySubset(
            ['kind' => Token::AT, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('@')
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACKET_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('[')
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACKET_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne(']')
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACE_L, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('{')
        );
        $this->assertArraySubset(
            ['kind' => Token::PIPE, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('|')
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACE_R, 'start' => 0, 'end' => 1, 'value' => null],
            (array) $this->lexOne('}')
        );
    }

    public function reportsUsefulUnknownCharErrors()
    {
        $unicode1 = json_decode('"\u203B"');
        $unicode2 = json_decode('"\u200b"');

        return [
            ['..', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \".\".\n\n1: ..\n   ^\n"],
            ['?', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"?\".\n\n1: ?\n   ^\n"],
            [$unicode1, "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"\\u203b\".\n\n1: $unicode1\n   ^\n"],
            [$unicode2, "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"\\u200b\".\n\n1: $unicode2\n   ^\n"],
        ];
    }

    /**
     * @dataProvider reportsUsefulUnknownCharErrors
     * @it lex reports useful unknown character error
     */
    public function testReportsUsefulUnknownCharErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lex reports useful information for dashes in names
     */
    public function testReportsUsefulDashesInfo()
    {
        $q = 'a-b';
        $lexer = new Lexer(new Source($q));
        $this->assertArraySubset(['kind' => Token::NAME, 'start' => 0, 'end' => 1, 'value' => 'a'], (array) $lexer->advance());

        $this->setExpectedException(SyntaxError::class, 'Syntax Error GraphQL (1:3) Invalid number, expected digit but got: "b"' . "\n\n1: a-b\n     ^\n");
        $lexer->advance();
    }

    /**
     * @it produces double linked list of tokens, including comments
     */
    public function testDoubleLinkedList()
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
            $this->assertNotEquals('Comment', $endToken->kind);
        } while ($endToken->kind !== '<EOF>');

        $this->assertEquals(null, $startToken->prev);
        $this->assertEquals(null, $endToken->next);

        $tokens = [];
        for ($tok = $startToken; $tok; $tok = $tok->next) {
            if (!empty($tokens)) {
                // Tokens are double-linked, prev should point to last seen token.
                $this->assertSame($tokens[count($tokens) - 1], $tok->prev);
            }
            $tokens[] = $tok;
        }

        $this->assertEquals([
            '<SOF>',
            '{',
            'Comment',
            'Name',
            '}',
            '<EOF>'
        ], Utils::map($tokens, function ($tok) {
            return $tok->kind;
        }));
    }

    /**
     * @param string $body
     * @return Token
     */
    private function lexOne($body)
    {
        $lexer = new Lexer(new Source($body));
        return $lexer->advance();
    }
}
