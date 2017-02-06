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

    public function __construct(Source $source, array $options = [])
    {
        $startOfFileToken = new Token(Token::SOF, 0, 0, 0, 0, null);

        $this->source = $source;
        $this->options = $options;
        $this->lastToken = $startOfFileToken;
        $this->token = $startOfFileToken;
        $this->line = 1;
        $this->lineStart = 0;
    }

    /**
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
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readToken(Token $prev)
    {
        $body = $this->source->body;
        $bodyLength = $this->source->length;

        $position = $this->positionAfterWhitespace($prev->end);
        $line = $this->line;
        $col = 1 + $position - $this->lineStart;

        if ($position >= $bodyLength) {
            return new Token(Token::EOF, $bodyLength, $bodyLength, $line, $col, $prev);
        }

        $code = Utils::charCodeAt($body, $position);

        // SourceCharacter
        if ($code < 0x0020 && $code !== 0x0009 && $code !== 0x000A && $code !== 0x000D) {
            throw new SyntaxError(
                $this->source,
                $position,
                'Cannot contain the invalid character ' . Utils::printCharCode($code)
            );
        }

        switch ($code) {
            case 33: // !
                return new Token(Token::BANG, $position, $position + 1, $line, $col, $prev);
            case 35: // #
                return $this->readComment($position, $line, $col, $prev);
            case 36: // $
                return new Token(Token::DOLLAR, $position, $position + 1, $line, $col, $prev);
            case 40: // (
                return new Token(Token::PAREN_L, $position, $position + 1, $line, $col, $prev);
            case 41: // )
                return new Token(Token::PAREN_R, $position, $position + 1, $line, $col, $prev);
            case 46: // .
                if (Utils::charCodeAt($body, $position+1) === 46 &&
                    Utils::charCodeAt($body, $position+2) === 46) {
                    return new Token(Token::SPREAD, $position, $position + 3, $line, $col, $prev);
                }
                break;
            case 58: // :
                return new Token(Token::COLON, $position, $position + 1, $line, $col, $prev);
            case 61: // =
                return new Token(Token::EQUALS, $position, $position + 1, $line, $col, $prev);
            case 64: // @
                return new Token(Token::AT, $position, $position + 1, $line, $col, $prev);
            case 91: // [
                return new Token(Token::BRACKET_L, $position, $position + 1, $line, $col, $prev);
            case 93: // ]
                return new Token(Token::BRACKET_R, $position, $position + 1, $line, $col, $prev);
            case 123: // {
                return new Token(Token::BRACE_L, $position, $position + 1, $line, $col, $prev);
            case 124: // |
                return new Token(Token::PIPE, $position, $position + 1, $line, $col, $prev);
            case 125: // }
                return new Token(Token::BRACE_R, $position, $position + 1, $line, $col, $prev);
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
                return $this->readName($position, $line, $col, $prev);
            // -
            case 45:
            // 0-9
            case 48: case 49: case 50: case 51: case 52:
            case 53: case 54: case 55: case 56: case 57:
                return $this->readNumber($position, $code, $line, $col, $prev);
            // "
            case 34:
                return $this->readString($position, $line, $col, $prev);
        }

        $errMessage = $code === 39
                    ? "Unexpected single quote character ('), did you mean to use ". 'a double quote (")?'
                    : 'Cannot parse the unexpected character ' . Utils::printCharCode($code) . '.';

        throw new SyntaxError(
            $this->source,
            $position,
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
        $body = $this->source->body;
        $bodyLength = $this->source->length;
        $end = $position + 1;

        while (
            $end !== $bodyLength &&
            ($code = Utils::charCodeAt($body, $end)) &&
            (
                $code === 95 || // _
                $code >= 48 && $code <= 57 || // 0-9
                $code >= 65 && $code <= 90 || // A-Z
                $code >= 97 && $code <= 122 // a-z
            )
        ) {
            ++$end;
        }
        return new Token(
            Token::NAME,
            $position,
            $end,
            $line,
            $col,
            $prev,
            mb_substr($body, $position, $end - $position, 'UTF-8')
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
        $body = $this->source->body;
        $position = $start;
        $isFloat = false;

        if ($code === 45) { // -
            $code = Utils::charCodeAt($body, ++$position);
        }

        // guard against leading zero's
        if ($code === 48) { // 0
            $code = Utils::charCodeAt($body, ++$position);

            if ($code >= 48 && $code <= 57) {
                throw new SyntaxError($this->source, $position, "Invalid number, unexpected digit after 0: " . Utils::printCharCode($code));
            }
        } else {
            $position = $this->readDigits($position, $code);
            $code = Utils::charCodeAt($body, $position);
        }

        if ($code === 46) { // .
            $isFloat = true;

            $code = Utils::charCodeAt($body, ++$position);
            $position = $this->readDigits($position, $code);
            $code = Utils::charCodeAt($body, $position);
        }

        if ($code === 69 || $code === 101) { // E e
            $isFloat = true;
            $code = Utils::charCodeAt($body, ++$position);

            if ($code === 43 || $code === 45) { // + -
                $code = Utils::charCodeAt($body, ++$position);
            }
            $position = $this->readDigits($position, $code);
        }

        return new Token(
            $isFloat ? Token::FLOAT : Token::INT,
            $start,
            $position,
            $line,
            $col,
            $prev,
            mb_substr($body, $start, $position - $start, 'UTF-8')
        );
    }

    /**
     * Returns the new position in the source after reading digits.
     */
    private function readDigits($start, $firstCode)
    {
        $body = $this->source->body;
        $position = $start;
        $code = $firstCode;

        if ($code >= 48 && $code <= 57) { // 0 - 9
            do {
                $code = Utils::charCodeAt($body, ++$position);
            } while ($code >= 48 && $code <= 57); // 0 - 9

            return $position;
        }

        if ($position > $this->source->length - 1) {
            $code = null;
        }

        throw new SyntaxError(
            $this->source,
            $position,
            'Invalid number, expected digit but got: ' . Utils::printCharCode($code)
        );
    }

    /**
     * @param int $start
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readString($start, $line, $col, Token $prev)
    {
        $body = $this->source->body;
        $bodyLength = $this->source->length;

        $position = $start + 1;
        $chunkStart = $position;
        $code = null;
        $value = '';

        while (
            $position < $bodyLength &&
            ($code = Utils::charCodeAt($body, $position)) &&
            // not LineTerminator
            $code !== 0x000A && $code !== 0x000D &&
            // not Quote (")
            $code !== 34
        ) {
            $this->assertValidStringCharacterCode($code, $position);

            ++$position;
            if ($code === 92) { // \
                $value .= mb_substr($body, $chunkStart, $position - 1 - $chunkStart, 'UTF-8');
                $code = Utils::charCodeAt($body, $position);
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
                        $hex = mb_substr($body, $position + 1, 4, 'UTF-8');
                        if (!preg_match('/[0-9a-fA-F]{4}/', $hex)) {
                            throw new SyntaxError(
                                $this->source,
                                $position,
                                'Invalid character escape sequence: \\u' . $hex
                            );
                        }
                        $code = hexdec($hex);
                        $this->assertValidStringCharacterCode($code, $position - 1);
                        $value .= Utils::chr($code);
                        $position += 4;
                        break;
                    default:
                        throw new SyntaxError(
                            $this->source,
                            $position,
                            'Invalid character escape sequence: \\' . Utils::chr($code)
                        );
                }
                ++$position;
                $chunkStart = $position;
            }
        }

        if ($code !== 34) {
            throw new SyntaxError(
                $this->source,
                $position,
                'Unterminated string.'
            );
        }

        $value .= mb_substr($body, $chunkStart, $position - $chunkStart, 'UTF-8');

        return new Token(
            Token::STRING,
            $start,
            $position + 1,
            $line,
            $col,
            $prev,
            $value
        );
    }

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
     * Reads from body starting at startPosition until it finds a non-whitespace
     * or commented character, then returns the position of that character for
     * lexing.
     *
     * @param $startPosition
     * @return int
     */
    private function positionAfterWhitespace($startPosition)
    {
        $body = $this->source->body;
        $bodyLength = $this->source->length;
        $position = $startPosition;

        while ($position < $bodyLength) {
            $code = Utils::charCodeAt($body, $position);

            // Skip whitespace
            // tab | space | comma | BOM
            if ($code === 9 || $code === 32 || $code === 44 || $code === 0xFEFF) {
                $position++;
            } else if ($code === 10) { // new line
                $position++;
                $this->line++;
                $this->lineStart = $position;
            } else if ($code === 13) { // carriage return
                if (Utils::charCodeAt($body, $position + 1) === 10) {
                    $position += 2;
                } else {
                    $position ++;
                }
                $this->line++;
                $this->lineStart = $position;
            } else {
                break;
            }
        }

        return $position;
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
        $body = $this->source->body;
        $position = $start;

        do {
            $code = Utils::charCodeAt($body, ++$position);
        } while (
            $code !== null &&
            // SourceCharacter but not LineTerminator
            ($code > 0x001F || $code === 0x0009)
        );

        return new Token(
            Token::COMMENT,
            $start,
            $position,
            $line,
            $col,
            $prev,
            mb_substr($body, $start + 1, $position - $start, 'UTF-8')
        );
    }
}
