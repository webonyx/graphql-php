<?php
namespace GraphQL\Language;

use GraphQL\SyntaxError;
use GraphQL\Utils;

// language/lexer.js

class Lexer
{
    /**
     * @var int
     */
    private $prevPosition;

    /**
     * @var Source
     */
    private $source;

    public function __construct(Source $source)
    {
        $this->prevPosition = 0;
        $this->source = $source;
    }

    /**
     * @param int|null $resetPosition
     * @return Token
     */
    public function nextToken($resetPosition = null)
    {
        $token = $this->readToken($resetPosition === null ? $this->prevPosition : $resetPosition);
        $this->prevPosition = $token->end;
        return $token;
    }

    /**
     * @param int $fromPosition
     * @return Token
     * @throws SyntaxError
     */
    private function readToken($fromPosition)
    {
        $body = $this->source->body;
        $bodyLength = $this->source->length;

        $position = $this->positionAfterWhitespace($body, $fromPosition);
        $code = Utils::charCodeAt($body, $position);

        if ($position >= $bodyLength) {
            return new Token(Token::EOF, $position, $position);
        }

        switch ($code) {
            // !
            case 33: return new Token(Token::BANG, $position, $position + 1);
            // $
            case 36: return new Token(Token::DOLLAR, $position, $position + 1);
            // (
            case 40: return new Token(Token::PAREN_L, $position, $position + 1);
            // )
            case 41: return new Token(Token::PAREN_R, $position, $position + 1);
            // .
            case 46:
                if (Utils::charCodeAt($body, $position+1) === 46 &&
                    Utils::charCodeAt($body, $position+2) === 46) {
                    return new Token(Token::SPREAD, $position, $position + 3);
                }
                break;
            // :
            case 58: return new Token(Token::COLON, $position, $position + 1);
            // =
            case 61: return new Token(Token::EQUALS, $position, $position + 1);
            // @
            case 64: return new Token(Token::AT, $position, $position + 1);
            // [
            case 91: return new Token(Token::BRACKET_L, $position, $position + 1);
            // ]
            case 93: return new Token(Token::BRACKET_R, $position, $position + 1);
            // {
            case 123: return new Token(Token::BRACE_L, $position, $position + 1);
            // |
            case 124: return new Token(Token::PIPE, $position, $position + 1);
            // }
            case 125: return new Token(Token::BRACE_R, $position, $position + 1);
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
            return $this->readName($position);
            // -
            case 45:
                // 0-9
            case 48: case 49: case 50: case 51: case 52:
            case 53: case 54: case 55: case 56: case 57:
            return $this->readNumber($position, $code);
            // "
            case 34: return $this->readString($position);
        }

        throw new SyntaxError($this->source, $position, 'Unexpected character "' . Utils::chr($code). '"');
    }

    /**
     * Reads an alphanumeric + underscore name from the source.
     *
     * [_A-Za-z][_0-9A-Za-z]*
     * @param int $position
     * @return Token
     */
    private function readName($position)
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
        return new Token(Token::NAME, $position, $end, mb_substr($body, $position, $end - $position, 'UTF-8'));
    }

    /**
     * Reads a number token from the source file, either a float
     * or an int depending on whether a decimal point appears.
     *
     * Int:   -?(0|[1-9][0-9]*)
     * Float: -?(0|[1-9][0-9]*)(\.[0-9]+)?((E|e)(+|-)?[0-9]+)?
     *
     * @param $start
     * @param $firstCode
     * @return Token
     * @throws SyntaxError
     */
    private function readNumber($start, $firstCode)
    {
        $code = $firstCode;
        $body = $this->source->body;
        $position = $start;
        $isFloat = false;

        if ($code === 45) { // -
            $code = Utils::charCodeAt($body, ++$position);
        }

        if ($code === 48) { // 0
            $code = Utils::charCodeAt($body, ++$position);
        } else if ($code >= 49 && $code <= 57) { // 1 - 9
            do {
                $code = Utils::charCodeAt($body, ++$position);
            } while ($code >= 48 && $code <= 57); // 0 - 9
        } else {
            throw new SyntaxError($this->source, $position, 'Invalid number');
        }

        if ($code === 46) { // .
            $isFloat = true;

            $code = Utils::charCodeAt($body, ++$position);
            if ($code >= 48 && $code <= 57) { // 0 - 9
                do {
                    $code = Utils::charCodeAt($body, ++$position);
                } while ($code >= 48 && $code <= 57); // 0 - 9
            } else {
                throw new SyntaxError($this->source, $position, 'Invalid number');
            }
        }

        if ($code === 69 || $code === 101) { // E e
            $isFloat = true;
            $code = Utils::charCodeAt($body, ++$position);

            if ($code === 43 || $code === 45) { // + -
                $code = Utils::charCodeAt($body, ++$position);
            }
            if ($code >= 48 && $code <= 57) { // 0 - 9
                do {
                    $code = Utils::charCodeAt($body, ++$position);
                } while ($code >= 48 && $code <= 57); // 0 - 9
            } else {
                throw new SyntaxError($this->source, $position, 'Invalid number');
            }
        }
        return new Token(
            $isFloat ? Token::FLOAT : Token::INT,
            $start,
            $position,
            mb_substr($body, $start, $position - $start, 'UTF-8')
        );
    }

    private function readString($start)
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
            $code !== 34 &&
            $code !== 10 && $code !== 13 && $code !== 0x2028 && $code !== 0x2029
        ) {
            ++$position;
            if ($code === 92) { // \
                $value .= mb_substr($body, $chunkStart, $position - 1 - $chunkStart, 'UTF-8');
                $code = Utils::charCodeAt($body, $position);
                switch ($code) {
                    case 34: $value .= '"'; break;
                    case 47: $value .= '\/'; break;
                    case 92: $value .= '\\'; break;
                    case 98: $value .= '\b'; break;
                    case 102: $value .= '\f'; break;
                    case 110: $value .= '\n'; break;
                    case 114: $value .= '\r'; break;
                    case 116: $value .= '\t'; break;
                    case 117:
                        $hex = mb_substr($body, $position + 1, 4);
                        if (!preg_match('/[0-9a-fA-F]{4}/', $hex)) {
                            throw new SyntaxError($this->source, $position, 'Bad character escape sequence');
                        }
                        $value .= Utils::chr(hexdec($hex));
                        $position += 4;
                        break;
                    default:
                        throw new SyntaxError($this->source, $position, 'Bad character escape sequence');
                }
                ++$position;
                $chunkStart = $position;
            }
        }

        if ($code !== 34) {
            throw new SyntaxError($this->source, $position, 'Unterminated string');
        }

        $value .= mb_substr($body, $chunkStart, $position - $chunkStart, 'UTF-8');
        return new Token(Token::STRING, $start, $position + 1, $value);
    }

    /**
     * Reads from body starting at startPosition until it finds a non-whitespace
     * or commented character, then returns the position of that character for
     * lexing.
     *
     * @param $body
     * @param $startPosition
     * @return int
     */
    private function positionAfterWhitespace($body, $startPosition)
    {
        $bodyLength = mb_strlen($body, 'UTF-8');
        $position = $startPosition;

        while ($position < $bodyLength) {
            $code = Utils::charCodeAt($body, $position);

            // Skip whitespace
            if (
                $code === 32 || // space
                $code === 44 || // comma
                $code === 160 || // '\xa0'
                $code === 0x2028 || // line separator
                $code === 0x2029 || // paragraph separator
                $code > 8 && $code < 14 // whitespace
            ) {
                ++$position;
                // Skip comments
            } else if ($code === 35) { // #
                ++$position;
                while (
                    $position < $bodyLength &&
                    ($code = Utils::charCodeAt($body, $position)) &&
                    $code !== 10 && $code !== 13 && $code !== 0x2028 && $code !== 0x2029
                ) {
                    ++$position;
                }
            } else {
                break;
            }
        }
        return $position;
    }
}
