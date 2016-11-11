<?php

namespace GraphQL\Tests\Language;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils;

class LexerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @it disallows uncommon control characters
     */
    public function testDissallowsUncommonControlCharacters()
    {
        try {
            $char = Utils::chr(0x0007);
            $this->lexOne($char);
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $error) {
            $msg = mb_substr($error->getMessage(),0, 53);
            $this->assertEquals(
                'Syntax Error GraphQL (1:1) Invalid character "\u0007"',
                $msg
            );
        }
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

        $this->assertArraySubset($expected, $this->lexOne($bom . ' foo')->toArray());
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
        $this->assertArraySubset($expected, $this->lexOne("\n \r\n \r  foo\n")->toArray());
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
        $this->assertArraySubset($expected, $this->lexOne($example1)->toArray());

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
        $this->assertArraySubset($expected, $this->lexOne($example2)->toArray());

        $expected = [
            'kind' => Token::NAME,
            'start' => 3,
            'end' => 6,
            'value' => 'foo'
        ];

        $example3 = ',,,foo,,,';
        $this->assertArraySubset($expected, $this->lexOne($example3)->toArray());
    }

    /**
     * @it errors respect whitespace
     */
    public function testErrorsRespectWhitespace()
    {
        $example = "

    ?


";
        try {
            $this->lexOne($example);
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $e) {
            $this->assertEquals(
                'Syntax Error GraphQL (3:5) Unexpected character "?"' . "\n" .
                "\n" .
                "2: \n" .
                "3:     ?\n" .
                "       ^\n" .
                "4: \n",
                $e->getMessage()
            );
       }
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
        ], $this->lexOne('"simple"')->toArray());


        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 15,
            'value' => ' white space '
        ], $this->lexOne('" white space "')->toArray());

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 10,
            'value' => 'quote "'
        ], $this->lexOne('"quote \\""')->toArray());

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 25,
            'value' => 'escaped \n\r\b\t\f'
        ], $this->lexOne('"escaped \\\\n\\\\r\\\\b\\\\t\\\\f"')->toArray());

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 16,
            'value' => 'slashes \\ \/'
        ], $this->lexOne('"slashes \\\\ \\\\/"')->toArray());

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 13,
            'value' => 'unicode яуц'
        ], $this->lexOne('"unicode яуц"')->toArray());

        $unicode = json_decode('"\u1234\u5678\u90AB\uCDEF"');
        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 34,
            'value' => 'unicode ' . $unicode
        ], $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"')->toArray());

        $this->assertArraySubset([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 26,
            'value' => $unicode
        ], $this->lexOne('"\u1234\u5678\u90AB\uCDEF"')->toArray());
    }

    /**
     * @it lex reports useful string errors
     */
    public function testReportsUsefulErrors()
    {
        $run = function($num, $str, $expectedMessage) {
            try {
                $this->lexOne($str);
                $this->fail('Expected exception not thrown in example: ' . $num);
            } catch (SyntaxError $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Test case $num failed");
            }
        };

        $run(1, '"', "Syntax Error GraphQL (1:2) Unterminated string\n\n1: \"\n    ^\n");
        $run(2, '"no end quote', "Syntax Error GraphQL (1:14) Unterminated string\n\n1: \"no end quote\n                ^\n");
        $run(3, '"contains unescaped \u0007 control char"', "Syntax Error GraphQL (1:21) Invalid character within String: \"\\u0007\"\n\n1: \"contains unescaped \\u0007 control char\"\n                       ^\n");
        $run(4, '"null-byte is not \u0000 end of file"', 'Syntax Error GraphQL (1:19) Invalid character within String: "\\u0000"'."\n\n1: \"null-byte is not \\u0000 end of file\"\n                     ^\n");
        $run(5, '"multi'."\n".'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(6, '"multi'."\r".'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(7, '"bad \\z esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\z\n\n1: \"bad \\z esc\"\n         ^\n");
        $run(8, '"bad \\x esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\x\n\n1: \"bad \\x esc\"\n         ^\n");
        $run(9, '"bad \\u1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u1 es\n\n1: \"bad \\u1 esc\"\n         ^\n");
        $run(10, '"bad \\u0XX1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u0XX1\n\n1: \"bad \\u0XX1 esc\"\n         ^\n");
        $run(11, '"bad \\uXXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXX\n\n1: \"bad \\uXXXX esc\"\n         ^\n");
        $run(12, '"bad \\uFXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uFXXX\n\n1: \"bad \\uFXXX esc\"\n         ^\n");
        $run(13, '"bad \\uXXXF esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXF\n\n1: \"bad \\uXXXF esc\"\n         ^\n");
    }

    /**
     * @it lexes numbers
     */
    public function testLexesNumbers()
    {
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '4'],
            $this->lexOne('4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '4.123'],
            $this->lexOne('4.123')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 2, 'value' => '-4'],
            $this->lexOne('-4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '9'],
            $this->lexOne('9')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '0'],
            $this->lexOne('0')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '-4.123'],
            $this->lexOne('-4.123')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '0.123'],
            $this->lexOne('0.123')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123e4'],
            $this->lexOne('123e4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123E4'],
            $this->lexOne('123E4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e-4'],
            $this->lexOne('123e-4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e+4'],
            $this->lexOne('123e+4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123e4'],
            $this->lexOne('-1.123e4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123E4'],
            $this->lexOne('-1.123E4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e-4'],
            $this->lexOne('-1.123e-4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e+4'],
            $this->lexOne('-1.123e+4')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::FLOAT, 'start' => 0, 'end' => 11, 'value' => '-1.123e4567'],
            $this->lexOne('-1.123e4567')->toArray()
        );
    }

    /**
     * @it lex reports useful number errors
     */
    public function testReportsUsefulNumberErrors()
    {
        $run = function($num, $str, $expectedMessage) {
            try {
                $this->lexOne($str);
                $this->fail('Expected exception not thrown in example: ' . $num);
            } catch (SyntaxError $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Test case $num failed");
            }
        };

        $run(0, '00', "Syntax Error GraphQL (1:2) Invalid number, unexpected digit after 0: \"0\"\n\n1: 00\n    ^\n");
        $run(1, '+1', "Syntax Error GraphQL (1:1) Unexpected character \"+\"\n\n1: +1\n   ^\n");
        $run(2, '1.', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: <EOF>\n\n1: 1.\n     ^\n");
        $run(3, '.123', "Syntax Error GraphQL (1:1) Unexpected character \".\"\n\n1: .123\n   ^\n");
        $run(4, '1.A', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: \"A\"\n\n1: 1.A\n     ^\n");
        $run(5, '-A', "Syntax Error GraphQL (1:2) Invalid number, expected digit but got: \"A\"\n\n1: -A\n    ^\n");
        $run(6, '1.0e', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: <EOF>\n\n1: 1.0e\n       ^\n");
        $run(7, '1.0eA', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: \"A\"\n\n1: 1.0eA\n       ^\n");
    }

    /**
     * @it lexes punctuation
     */
    public function testLexesPunctuation()
    {
        $this->assertArraySubset(
            ['kind' => Token::BANG, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('!')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::DOLLAR, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('$')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::PAREN_L, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('(')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::PAREN_R, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne(')')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::SPREAD, 'start' => 0, 'end' => 3, 'value' => null],
            $this->lexOne('...')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::COLON, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne(':')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::EQUALS, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('=')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::AT, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('@')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACKET_L, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('[')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACKET_R, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne(']')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACE_L, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('{')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::PIPE, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('|')->toArray()
        );
        $this->assertArraySubset(
            ['kind' => Token::BRACE_R, 'start' => 0, 'end' => 1, 'value' => null],
            $this->lexOne('}')->toArray()
        );
    }

    /**
     * @it lex reports useful unknown character error
     */
    public function testReportsUsefulUnknownCharErrors()
    {
        $run = function($num, $str, $expectedMessage) {
            try {
                $this->lexOne($str);
                $this->fail('Expected exception not thrown in example: ' . $num);
            } catch (SyntaxError $e) {
                $this->assertEquals($expectedMessage, $e->getMessage(), "Test case $num failed");
            }
        };
        $run(1, '..', "Syntax Error GraphQL (1:1) Unexpected character \".\"\n\n1: ..\n   ^\n");
        $run(2, '?', "Syntax Error GraphQL (1:1) Unexpected character \"?\"\n\n1: ?\n   ^\n");

        $unicode = json_decode('"\u203B"');
        $run(3, $unicode, "Syntax Error GraphQL (1:1) Unexpected character \"\\u203b\"\n\n1: $unicode\n   ^\n");

        $unicode = json_decode('"\u200b"');
        $run(4, $unicode, "Syntax Error GraphQL (1:1) Unexpected character \"\\u200b\"\n\n1: $unicode\n   ^\n");
    }

    /**
     * @it lex reports useful information for dashes in names
     */
    public function testReportsUsefulDashesInfo()
    {
        $q = 'a-b';
        $lexer = new Lexer();
        $lexer->setSource(new Source($q));
        $this->assertArraySubset(['kind' => Token::NAME, 'start' => 0, 'end' => 1, 'value' => 'a'], $lexer->advance()->toArray());

        try {
            $lexer->advance();
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $err) {
            $this->assertEquals('Syntax Error GraphQL (1:3) Invalid number, expected digit but got: "b"'."\n\n1: a-b\n     ^\n", $err->getMessage());
        }
    }

    /**
     * @it produces double linked list of tokens, including comments
     */
    public function testDoubleLinkedList()
    {
        $lexer = new Lexer();
        $lexer->setSource(new Source('{
      #comment
      field
    }'));

        $startToken = $lexer->token;
        do {
            $endToken = $lexer->advance();
            // Lexer advances over ignored comment tokens to make writing parsers
            // easier, but will include them in the linked list result.
            $this->assertNotEquals('Comment', $endToken->getKind());
        } while ($endToken->getKind() !== '<EOF>');

        $this->assertEquals(null, $startToken->getPrev());
        $this->assertEquals(null, $endToken->next);

        $tokens = [];
        for ($tok = $startToken; $tok; $tok = $tok->next) {
            if (!empty($tokens)) {
                // Tokens are double-linked, prev should point to last seen token.
                $this->assertSame($tokens[count($tokens) - 1], $tok->getPrev());
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
        ], Utils::map($tokens, function (Token $tok) {
            return $tok->getKind();
        }));
    }

    /**
     * @param string $body
     * @return Token
     */
    private function lexOne($body)
    {
        $lexer = new Lexer();
        $lexer->setSource(new Source($body));

        return $lexer->advance();
    }
}
