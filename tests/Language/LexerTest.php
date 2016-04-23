<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\Lexer;
use GraphQL\Language\Source;
use GraphQL\Language\Token;
use GraphQL\SyntaxError;
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
            $this->lexErr($char);
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
        $this->assertEquals(new Token(Token::NAME, 2, 5, 'foo'), $this->lexOne($bom . ' foo'));
    }

    /**
     * @it skips whitespace
     */
    public function testSkipsWhitespaces()
    {
        $example1 = '

    foo


';
        $this->assertEquals(new Token(Token::NAME, 6, 9, 'foo'), $this->lexOne($example1));

        $example2 = '
    #comment
    foo#comment
';

        $this->assertEquals(new Token(Token::NAME, 18, 21, 'foo'), $this->lexOne($example2));

        $example3 = ',,,foo,,,';
        $this->assertEquals(new Token(Token::NAME, 3, 6, 'foo'), $this->lexOne($example3));
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
            $this->lexErr($example);
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
        $this->assertEquals(new Token(Token::STRING, 0, 8, 'simple'), $this->lexOne('"simple"'));
        $this->assertEquals(new Token(Token::STRING, 0, 15, ' white space '), $this->lexOne('" white space "'));
        $this->assertEquals(new Token(Token::STRING, 0, 10, 'quote "'), $this->lexOne('"quote \\""'));
        $this->assertEquals(new Token(Token::STRING, 0, 25, 'escaped \n\r\b\t\f'), $this->lexOne('"escaped \\\\n\\\\r\\\\b\\\\t\\\\f"'));
        $this->assertEquals(new Token(Token::STRING, 0, 16, 'slashes \\ \/'), $this->lexOne('"slashes \\\\ \\\\/"'));

        $this->assertEquals(new Token(Token::STRING, 0, 13, 'unicode яуц'), $this->lexOne('"unicode яуц"'));

        $unicode = json_decode('"\u1234\u5678\u90AB\uCDEF"');
        $this->assertEquals(new Token(Token::STRING, 0, 34, 'unicode ' . $unicode), $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"'));
        $this->assertEquals(new Token(Token::STRING, 0, 26, $unicode), $this->lexOne('"\u1234\u5678\u90AB\uCDEF"'));
    }

    /**
     * @it lex reports useful string errors
     */
    public function testReportsUsefulErrors()
    {
        $run = function($num, $str, $expectedMessage) {
            try {
                $this->lexErr($str);
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
        $this->assertEquals(
            new Token(Token::INT, 0, 1, '4'),
            $this->lexOne('4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 5, '4.123'),
            $this->lexOne('4.123')
        );
        $this->assertEquals(
            new Token(Token::INT, 0, 2, '-4'),
            $this->lexOne('-4')
        );
        $this->assertEquals(
            new Token(Token::INT, 0, 1, '9'),
            $this->lexOne('9')
        );
        $this->assertEquals(
            new Token(Token::INT, 0, 1, '0'),
            $this->lexOne('0')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 6, '-4.123'),
            $this->lexOne('-4.123')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 5, '0.123'),
            $this->lexOne('0.123')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 5, '123e4'),
            $this->lexOne('123e4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 5, '123E4'),
            $this->lexOne('123E4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 6, '123e-4'),
            $this->lexOne('123e-4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 6, '123e+4'),
            $this->lexOne('123e+4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 8, '-1.123e4'),
            $this->lexOne('-1.123e4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 8, '-1.123E4'),
            $this->lexOne('-1.123E4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 9, '-1.123e-4'),
            $this->lexOne('-1.123e-4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 9, '-1.123e+4'),
            $this->lexOne('-1.123e+4')
        );
        $this->assertEquals(
            new Token(Token::FLOAT, 0, 11, '-1.123e4567'),
            $this->lexOne('-1.123e4567')
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
        $this->assertEquals(
            new Token(Token::BANG, 0, 1, null),
            $this->lexOne('!')
        );
        $this->assertEquals(
            new Token(Token::DOLLAR, 0, 1, null),
            $this->lexOne('$')
        );
        $this->assertEquals(
            new Token(Token::PAREN_L, 0, 1, null),
            $this->lexOne('(')
        );
        $this->assertEquals(
            new Token(Token::PAREN_R, 0, 1, null),
            $this->lexOne(')')
        );
        $this->assertEquals(
            new Token(Token::SPREAD, 0, 3, null),
            $this->lexOne('...')
        );
        $this->assertEquals(
            new Token(Token::COLON, 0, 1, null),
            $this->lexOne(':')
        );
        $this->assertEquals(
            new Token(Token::EQUALS, 0, 1, null),
            $this->lexOne('=')
        );
        $this->assertEquals(
            new Token(Token::AT, 0, 1, null),
            $this->lexOne('@')
        );
        $this->assertEquals(
            new Token(Token::BRACKET_L, 0, 1, null),
            $this->lexOne('[')
        );
        $this->assertEquals(
            new Token(Token::BRACKET_R, 0, 1, null),
            $this->lexOne(']')
        );
        $this->assertEquals(
            new Token(Token::BRACE_L, 0, 1, null),
            $this->lexOne('{')
        );
        $this->assertEquals(
            new Token(Token::PIPE, 0, 1, null),
            $this->lexOne('|')
        );
        $this->assertEquals(
            new Token(Token::BRACE_R, 0, 1, null),
            $this->lexOne('}')
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
        $lexer = new Lexer(new Source($q));
        $this->assertEquals(new Token(Token::NAME, 0, 1, 'a'), $lexer->nextToken());

        try {
            $lexer->nextToken();
            $this->fail('Expected exception not thrown');
        } catch (SyntaxError $err) {
            $this->assertEquals('Syntax Error GraphQL (1:3) Invalid number, expected digit but got: "b"'."\n\n1: a-b\n     ^\n", $err->getMessage());
        }
    }

    /**
     * @param string $body
     * @return Token
     */
    private function lexOne($body)
    {
        $lexer = new Lexer(new Source($body));
        return $lexer->nextToken();
    }

    /**
     * @param $body
     * @return Token
     */
    private function lexErr($body)
    {
        $lexer = new Lexer(new Source($body));
        return $lexer->nextToken();
    }
}
