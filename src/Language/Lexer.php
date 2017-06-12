<?php
namespace GraphQL\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Utils;

/**
 * A Lexer is a stateful stream generator in that every time
 * it is advanced, it returns the next token in the Source. Assuming the
 * source lexes, the final Token emitted by the lexer will be of kind
 * EOF, after which the lexer will repeatedly return the same EOF token
 * whenever called.
 */
class Lexer
{
    /**
     * @var Source
     */
    public $source;

    /**
     * @var array
     */
    public $options;

    /**
     * The previously focused non-ignored token.
     *
     * @var Token
     */
    public $lastToken;

    /**
     * The currently focused non-ignored token.
     *
     * @var Token
     */
    public $token;

    /**
     * The (1-indexed) line containing the current token.
     *
     * @var int
     */
    public $line;

    /**
     * The character offset at which the current line begins.
     *
     * @var int
     */
    public $lineStart;

    /**
     * The current parse position of the lexer.
     *
     * @var int
     */
    private $position;

    /**
     * Lexer constructor.
     * @param Source $source
     * @param array $options
     */
    public function __construct(Source $source, array $options = [])
    {
        $startOfFileToken = new Token(Token::SOF, 0, 0, 0, 0, null);

        $this->source = $source;
        $this->options = $options;
        $this->lastToken = $startOfFileToken;
        $this->token = $startOfFileToken;
        $this->line = 1;
        $this->lineStart = 0;
        $this->position = 0;
    }

    /**
     * Advance the lexer and return the next token in the stream.
     *
     * @return Token
     */
    public function advance()
    {
        $token = $this->lastToken = $this->token;

        if ($token->kind !== Token::EOF) {
            do {
                $token = $token->next = $this->readToken($token);
            } while ($token->kind === Token::COMMENT);
            $this->token = $token;
        }
        return $token;
    }

    /**
     * @return Token
     */
    public function nextToken()
    {
        trigger_error(__METHOD__ . ' is deprecated in favor of advance()', E_USER_DEPRECATED);
        return $this->advance();
    }

    /**
     * Read a token from the internal buffer.
     *
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readToken(Token $prev)
    {
        $buf = $this->source->buf;
        $bufLength = $this->source->length;

        $this->readWhitespace();
        $line = $this->line;
        $col = 1 + $this->position - $this->lineStart;

        if ($this->position >= $bufLength) {
            return new Token(Token::EOF, $bufLength, $bufLength, $line, $col, $prev);
        }

        $code = $buf[$this->position];

        // SourceCharacter
        if ($code < 0x0020 && $code !== 0x0009 && $code !== 0x000A && $code !== 0x000D) {
            throw new SyntaxError(
                $this->source,
                $this->position,
                'Cannot contain the invalid character ' . Utils::printCharCode($code)
            );
        }

        switch ($code) {
            case 33: // !
                return new Token(Token::BANG, $this->position, ++$this->position, $line, $col, $prev);
            case 35: // #
                return $this->readComment($this->position, $line, $col, $prev);
            case 36: // $
                return new Token(Token::DOLLAR, $this->position, ++$this->position, $line, $col, $prev);
            case 40: // (
                return new Token(Token::PAREN_L, $this->position, ++$this->position, $line, $col, $prev);
            case 41: // )
                return new Token(Token::PAREN_R, $this->position, ++$this->position, $line, $col, $prev);
            case 46: // .
                if ($this->source->length - $this->position > 2 &&
                    $buf[$this->position+1] === 46 &&
                    $buf[$this->position+2] === 46) {
                    $this->position += 3;
                    return new Token(Token::SPREAD, $this->position - 3, $this->position, $line, $col, $prev);
                }
                break;
            case 58: // :
                return new Token(Token::COLON, $this->position, ++$this->position, $line, $col, $prev);
            case 61: // =
                return new Token(Token::EQUALS, $this->position, ++$this->position, $line, $col, $prev);
            case 64: // @
                return new Token(Token::AT, $this->position, ++$this->position, $line, $col, $prev);
            case 91: // [
                return new Token(Token::BRACKET_L, $this->position, ++$this->position, $line, $col, $prev);
            case 93: // ]
                return new Token(Token::BRACKET_R, $this->position, ++$this->position, $line, $col, $prev);
            case 123: // {
                return new Token(Token::BRACE_L, $this->position, ++$this->position, $line, $col, $prev);
            case 124: // |
                return new Token(Token::PIPE, $this->position, ++$this->position, $line, $col, $prev);
            case 125: // }
                return new Token(Token::BRACE_R, $this->position, ++$this->position, $line, $col, $prev);
            // A-Z
            case 65: case 66: case 67: case 68: case 69: case 70: case 71: case 72:
            case 73: case 74: case 75: case 76: case 77: case 78: case 79: case 80:
            case 81: case 82: case 83: case 84: case 85: case 86: case 87: case 88:
            case 89: case 90:
            // _
            case 95:
            // a-z
            case 97: case 98: case 99: case 100: case 101: case 102: case 103: case 104:
            case 105: case 106: case 107: case 108: case 109: case 110: case 111:
            case 112: case 113: case 114: case 115: case 116: case 117: case 118:
            case 119: case 120: case 121: case 122:
                return $this->readName($this->position, $line, $col, $prev);
            // -
            case 45:
            // 0-9
            case 48: case 49: case 50: case 51: case 52:
            case 53: case 54: case 55: case 56: case 57:
                return $this->readNumber($this->position, $code, $line, $col, $prev);
            // "
            case 34:
                return $this->readString($this->position, $line, $col, $prev);
        }

        $errMessage = $code === 39
                    ? "Unexpected single quote character ('), did you mean to use ". 'a double quote (")?'
                    : 'Cannot parse the unexpected character ' . Utils::printCharCode($code) . '.';

        throw new SyntaxError(
            $this->source,
            $this->position,
            $errMessage
        );
    }

    /**
     * Reads an alphanumeric + underscore name from the source.
     *
     * [_A-Za-z][_0-9A-Za-z]*
     *
     * @param int $position
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     */
    private function readName($position, $line, $col, Token $prev)
    {
        $buf = $this->source->buf;

        $code = $this->currentCode();
        $value = '';

        while (
            $this->position < $this->source->length &&
            ($code = $buf[$this->position]) &&
            (
                $code === 95 || // _
                $code >= 48 && $code <= 57 || // 0-9
                $code >= 65 && $code <= 90 || // A-Z
                $code >= 97 && $code <= 122 // a-z
            )
        ) {
            $value .= Utils::chr($code);
            $this->position++;
        }
        return new Token(
            Token::NAME,
            $position,
            $this->position,
            $line,
            $col,
            $prev,
            $value
        );
    }

    /**
     * Reads a number token from the source file, either a float
     * or an int depending on whether a decimal point appears.
     *
     * Int:   -?(0|[1-9][0-9]*)
     * Float: -?(0|[1-9][0-9]*)(\.[0-9]+)?((E|e)(+|-)?[0-9]+)?
     *
     * @param int $start
     * @param string $firstCode
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readNumber($start, $firstCode, $line, $col, Token $prev)
    {
        $code = $firstCode;
        $buf = $this->source->buf;
        $isFloat = false;

        $value = '';

        if ($code === 45) { // -
            $value = chr($buf[$this->position++]);
            $code = $buf[$this->position];
        }

        // guard against leading zero's
        if ($code === 48) { // 0
            $value .= chr($buf[$this->position++]);

            if ($this->position < $this->source->length) {
                $code = $buf[$this->position];

                if ($code >= 48 && $code <= 57) {
                    throw new SyntaxError($this->source, $this->position, "Invalid number, unexpected digit after 0: " . Utils::printCharCode($code));
                }
            }
        } else {
            $value .= $this->readDigits($this->position);
            $code = $this->currentCode();
        }

        if ($code === 46) { // .
            $isFloat = true;
            $value .= chr($buf[$this->position++]);
            $value .= $this->readDigits($this->position);
            $code = $this->currentCode();
        }

        if ($code === 69 || $code === 101) { // E e
            $isFloat = true;
            $value .= chr($buf[$this->position++]);
            $code = $this->currentCode();

            if ($code === 43 || $code === 45) { // + -
                $value .= chr($buf[$this->position++]);
            }
            $value .= $this->readDigits($this->position);
        }

        return new Token(
            $isFloat ? Token::FLOAT : Token::INT,
            $start,
            $this->position,
            $line,
            $col,
            $prev,
            $value
        );
    }

    /**
     * Return a series of digits.
     *
     * @param $start
     * @return string
     * @throws SyntaxError
     */
    private function readDigits($start)
    {
        $buf = $this->source->buf;
        $code = $this->position < $this->source->length? $buf[$this->position] : '<EOF>';

        $value = '';

        // Throw if out of range.
        if ($code < 48 || $code > 57) {
            if ($this->position > $this->source->length - 1) {
                $code = null;
            }

            throw new SyntaxError(
                $this->source,
                $start,
                'Invalid number, expected digit but got: ' . Utils::printCharCode($code)
            );
        }

        do {
            $value .= chr($code);
            $this->position++;
        } while (
            $this->position < $this->source->length &&
            ($code = $buf[$this->position]) &&
            $code >= 48 && $code <= 57 // 0 - 9
        );

        return $value;
    }

    /**
     * Read a string token from the buffer.
     *
     * @param int $start
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readString($start, $line, $col, Token $prev)
    {
        $buf = $this->source->buf;
        $bodyLength = $this->source->length;

        $code = null;
        $value = '';
        $positionOffset = 1;

        while (
            $this->position < $bodyLength - 1 &&
            ($code = $buf[++$this->position]) &&
            // not Quote (")
            $code !== 34
        ) {
            if($code === 0x000A || $code === 0x000D) {
                // Modify position offset and break.
                $positionOffset--;
                break;
            }

            $this->assertValidStringCharacterCode($code, $this->position);

            if ($code === 92) { // \
                $code = $buf[++$this->position];
                switch ($code) {
                    case 34: $value .= '"'; break;
                    case 47: $value .= '/'; break;
                    case 92: $value .= '\\'; break;
                    case 98: $value .= chr(8); break; // \b (backspace)
                    case 102: $value .= "\f"; break;
                    case 110: $value .= "\n"; break;
                    case 114: $value .= "\r"; break;
                    case 116: $value .= "\t"; break;
                    case 117:
                        $hex  = chr($buf[++$this->position]);
                        $hex .= chr($buf[++$this->position]);
                        $hex .= chr($buf[++$this->position]);
                        $hex .= chr($buf[++$this->position]);
                        if (!preg_match('/[0-9a-fA-F]{4}/', $hex)) {
                            throw new SyntaxError(
                                $this->source,
                                $this->position - 4,
                                'Invalid character escape sequence: \\u' . $hex
                            );
                        }
                        $code = hexdec($hex);
                        $this->assertValidStringCharacterCode($code, $this->position - 5);
                        $value .= Utils::chr($code);
                        break;
                    default:
                        throw new SyntaxError(
                            $this->source,
                            $this->position,
                            'Invalid character escape sequence: \\' . Utils::chr($code)
                        );
                }
            } else {
                $value .= Utils::chr($code);
            }
        }

        if ($code !== 34) {
            throw new SyntaxError(
                $this->source,
                $this->position + $positionOffset,
                'Unterminated string.'
            );
        }

        // Increment position past the terminating " character.
        $this->position++;

        return new Token(
            Token::STRING,
            $start,
            $this->position,
            $line,
            $col,
            $prev,
            $value
        );
    }

    /**
     * Assert that a code is a valid character code.
     *
     * @param $code
     * @param $position
     * @throws SyntaxError
     */
    private function assertValidStringCharacterCode($code, $position)
    {
        // SourceCharacter
        if ($code < 0x0020 && $code !== 0x0009) {
            throw new SyntaxError(
                $this->source,
                $position,
                'Invalid character within String: ' . Utils::printCharCode($code)
            );
        }
    }

    /**
     * Increments the buffer position until it finds a non-whitespace
     * or commented character
     *
     * @return void
     */
    private function readWhitespace()
    {
        $buf = $this->source->buf;
        $bodyLength = $this->source->length;

        while ($this->position < $bodyLength) {
            $code = $buf[$this->position];

            // Skip whitespace
            // tab | space | comma | BOM
            if ($code === 9 || $code === 32 || $code === 44 || $code === 0xFEFF) {
                $this->position++;
            } else if ($code === 10) { // new line
                $this->position++;
                $this->line++;
                $this->lineStart = $this->position;
            } else if ($code === 13) { // carriage return
                if ($buf[$this->position + 1] === 10) {
                    $this->position += 2;
                } else {
                    $this->position ++;
                }
                $this->line++;
                $this->lineStart = $this->position;
            } else {
                break;
            }
        }
    }

    /**
     * Reads a comment token from the source file.
     *
     * #[\u0009\u0020-\uFFFF]*
     *
     * @param $start
     * @param $line
     * @param $col
     * @param Token $prev
     * @return Token
     */
    private function readComment($start, $line, $col, Token $prev)
    {
        $value = '';
        $this->position++;

        while (
            $this->position < $this->source->length &&
            ($code = $this->currentCode()) &&
            ($code > 0x001F || $code === 0x0009)
         ) {
            $value .= chr($code);
            $this->position++;
        }

        return new Token(
            Token::COMMENT,
            $start,
            $this->position,
            $line,
            $col,
            $prev,
            $value
        );
    }

    /**
     * Retrieve the current code from our internal buffer, or EOF if at the end.
     *
     * @return string
     */
    private function currentCode() {
        return $this->position < $this->source->length ?
            $this->source->buf[$this->position] :
            '<EOF>';
    }
}
