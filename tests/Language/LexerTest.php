<?php
namespace GraphQL\Language;

use GraphQL\SyntaxError;

class LexerTest extends \PHPUnit_Framework_TestCase
{
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

    public function testLexesStrings()
    {
        $this->assertEquals(new Token(Token::STRING, 0, 8, 'simple'), $this->lexOne('"simple"'));
        $this->assertEquals(new Token(Token::STRING, 0, 15, ' white space '), $this->lexOne('" white space "'));
        $this->assertEquals(new Token(Token::STRING, 0, 10, 'quote "'), $this->lexOne('"quote \\""'));
        $this->assertEquals(new Token(Token::STRING, 0, 20, 'escaped \n\r\b\t\f'), $this->lexOne('"escaped \\n\\r\\b\\t\\f"'));
        $this->assertEquals(new Token(Token::STRING, 0, 15, 'slashes \\ \/'), $this->lexOne('"slashes \\\\ \\/"'));

        $this->assertEquals(new Token(Token::STRING, 0, 13, 'unicode яуц'), $this->lexOne('"unicode яуц"'));

        $unicode = json_decode('"\u1234\u5678\u90AB\uCDEF"');
        $this->assertEquals(new Token(Token::STRING, 0, 34, 'unicode ' . $unicode), $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"'));
    }

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

        $run(1, '"no end quote', "Syntax Error GraphQL (1:14) Unterminated string\n\n1: \"no end quote\n                ^\n");
        $run(2, '"multi'."\n".'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(3, '"multi'."\r".'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(4, '"multi' . json_decode('"\u2028"') . 'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(5, '"multi' . json_decode('"\u2029"') . 'line"', "Syntax Error GraphQL (1:7) Unterminated string\n\n1: \"multi\n         ^\n2: line\"\n");
        $run(6, '"bad \\z esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\z esc\"\n         ^\n");
        $run(7, '"bad \\x esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\x esc\"\n         ^\n");
        $run(8, '"bad \\u1 esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\u1 esc\"\n         ^\n");
        $run(9, '"bad \\u0XX1 esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\u0XX1 esc\"\n         ^\n");
        $run(10, '"bad \\uXXXX esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\uXXXX esc\"\n         ^\n");
        $run(11, '"bad \\uFXXX esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\uFXXX esc\"\n         ^\n");
        $run(12, '"bad \\uXXXF esc"', "Syntax Error GraphQL (1:7) Bad character escape sequence\n\n1: \"bad \\uXXXF esc\"\n         ^\n");
    }

    public function testLexesNumbers()
    {
        // lexes numbers
/*
        $this->assertEquals(
            new Token(Token::STRING, 0, 8, 'simple'),
            $this->lexOne('"simple"')
        );
        $this->assertEquals(
            new Token(Token::STRING, 0, 15, ' white space '),
            $this->lexOne('" white space "')
        );
        $this->assertEquals(
            new Token(Token::STRING, 0, 20, 'escaped \n\r\b\t\f'),
            $this->lexOne('"escaped \\n\\r\\b\\t\\f"')
        );
        $this->assertEquals(
            new Token(Token::STRING, 0, 15, 'slashes \\ \/'),
            $this->lexOne('"slashes \\\\ \\/"')
        );
        $this->assertEquals(
            new Token(Token::STRING, 0, 34, 'unicode ' . json_decode('"\u1234\u5678\u90AB\uCDEF"')),
            $this->lexOne('"unicode \\u1234\\u5678\\u90AB\\uCDEF"')
        );*/

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
            new Token(Token::INT, 0, 1, '0'),
            $this->lexOne('00')
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

        $run(1, '+1', "Syntax Error GraphQL (1:1) Unexpected character \"+\"\n\n1: +1\n   ^\n");
        $run(2, '1.', "Syntax Error GraphQL (1:3) Invalid number\n\n1: 1.\n     ^\n");
        $run(3, '.123', "Syntax Error GraphQL (1:1) Unexpected character \".\"\n\n1: .123\n   ^\n");
        $run(4, '1.A', "Syntax Error GraphQL (1:3) Invalid number\n\n1: 1.A\n     ^\n");
        $run(5, '-A', "Syntax Error GraphQL (1:2) Invalid number\n\n1: -A\n    ^\n");
        $run(6, '1.0e', "Syntax Error GraphQL (1:5) Invalid number\n\n1: 1.0e\n       ^\n");
        $run(7, '1.0eA', "Syntax Error GraphQL (1:5) Invalid number\n\n1: 1.0eA\n       ^\n");
    }

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
            new Token(Token::BRACE_R, 0, 1, null),
            $this->lexOne('}')
        );
        $this->assertEquals(
            new Token(Token::PIPE, 0, 1, null),
            $this->lexOne('|')
        );
    }

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
        $run(3, $unicode, "Syntax Error GraphQL (1:1) Unexpected character \"$unicode\"\n\n1: $unicode\n   ^\n");
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
}
