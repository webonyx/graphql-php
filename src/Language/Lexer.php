<?php
namespace GraphQL\Language;

use GraphQL\Error\SyntaxError;
use GraphQL\Utils\Utils;

/**
 * A Lexer is a stateful stream generator in that every time
 * it is advanced, it returns the next token in the Source. Assuming the
 * source lexes, the final Token emitted by the lexer will be of kind
 * EOF, after which the lexer will repeatedly return the same EOF token
 * whenever called.
 *
 * Algorithm is O(N) both on memory and time
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
     * Current cursor position for UTF8 encoding of the source
     *
     * @var int
     */
    private $position;

    /**
     * Current cursor position for ASCII representation of the source
     *
     * @var int
     */
    private $byteStreamPosition;

    /**
     * Lexer constructor.
     *
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
        $this->position = $this->byteStreamPosition = 0;
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
        $bodyLength = $this->source->length;

        $this->positionAfterWhitespace();
        $position = $this->position;

        $line = $this->line;
        $col = 1 + $position - $this->lineStart;

        if ($position >= $bodyLength) {
            return new Token(Token::EOF, $bodyLength, $bodyLength, $line, $col, $prev);
        }

        // Read next char and advance string cursor:
        list (, $code, $bytes) = $this->readChar(true);

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
                $this->moveStringCursor(-1, -1 * $bytes);
                return $this->readComment($line, $col, $prev);
            case 36: // $
                return new Token(Token::DOLLAR, $position, $position + 1, $line, $col, $prev);
            case 40: // (
                return new Token(Token::PAREN_L, $position, $position + 1, $line, $col, $prev);
            case 41: // )
                return new Token(Token::PAREN_R, $position, $position + 1, $line, $col, $prev);
            case 46: // .
                list (, $charCode1) = $this->readChar(true);
                list (, $charCode2) = $this->readChar(true);

                if ($charCode1 === 46 && $charCode2 === 46) {
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
                return $this->moveStringCursor(-1, -1 * $bytes)
                    ->readName($line, $col, $prev);
            // -
            case 45:
            // 0-9
            case 48: case 49: case 50: case 51: case 52:
            case 53: case 54: case 55: case 56: case 57:
                return $this->moveStringCursor(-1, -1 * $bytes)
                    ->readNumber($line, $col, $prev);
            // "
            case 34:
                return $this->moveStringCursor(-1, -1 * $bytes)
                    ->readString($line, $col, $prev);
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
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     */
    private function readName($line, $col, Token $prev)
    {
        $value = '';
        $start = $this->position;
        list ($char, $code) = $this->readChar();

        while ($code && (
            $code === 95 || // _
            $code >= 48 && $code <= 57 || // 0-9
            $code >= 65 && $code <= 90 || // A-Z
            $code >= 97 && $code <= 122 // a-z
        )) {
            $value .= $char;
            list ($char, $code) = $this->moveStringCursor(1, 1)->readChar();
        }
        return new Token(
            Token::NAME,
            $start,
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
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readNumber($line, $col, Token $prev)
    {
        $value = '';
        $start = $this->position;
        list ($char, $code) = $this->readChar();

        $isFloat = false;

        if ($code === 45) { // -
            $value .= $char;
            list ($char, $code) = $this->moveStringCursor(1, 1)->readChar();
        }

        // guard against leading zero's
        if ($code === 48) { // 0
            $value .= $char;
            list ($char, $code) = $this->moveStringCursor(1, 1)->readChar();

            if ($code >= 48 && $code <= 57) {
                throw new SyntaxError($this->source, $this->position, "Invalid number, unexpected digit after 0: " . Utils::printCharCode($code));
            }
        } else {
            $value .= $this->readDigits();
            list ($char, $code) = $this->readChar();
        }

        if ($code === 46) { // .
            $isFloat = true;
            $this->moveStringCursor(1, 1);

            $value .= $char;
            $value .= $this->readDigits();
            list ($char, $code) = $this->readChar();
        }

        if ($code === 69 || $code === 101) { // E e
            $isFloat = true;
            $value .= $char;
            list ($char, $code) = $this->moveStringCursor(1, 1)->readChar();

            if ($code === 43 || $code === 45) { // + -
                $value .= $char;
                $this->moveStringCursor(1, 1);
            }
            $value .= $this->readDigits();
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
     * Returns string with all digits + changes current string cursor position to point to the first char after digits
     */
    private function readDigits()
    {
        list ($char, $code) = $this->readChar();

        if ($code >= 48 && $code <= 57) { // 0 - 9
            $value = '';

            do {
                $value .= $char;
                list ($char, $code) = $this->moveStringCursor(1, 1)->readChar();
            } while ($code >= 48 && $code <= 57); // 0 - 9

            return $value;
        }

        if ($this->position > $this->source->length - 1) {
            $code = null;
        }

        throw new SyntaxError(
            $this->source,
            $this->position,
            'Invalid number, expected digit but got: ' . Utils::printCharCode($code)
        );
    }

    /**
     * @param int $line
     * @param int $col
     * @param Token $prev
     * @return Token
     * @throws SyntaxError
     */
    private function readString($line, $col, Token $prev)
    {
        $start = $this->position;

        // Skip leading quote and read first string char:
        list ($char, $code, $bytes) = $this->moveStringCursor(1, 1)->readChar();

        $chunk = '';
        $value = '';

        while (
            $code &&
            // not LineTerminator
            $code !== 10 && $code !== 13 &&
            // not Quote (")
            $code !== 34
        ) {
            $this->assertValidStringCharacterCode($code, $this->position);
            $this->moveStringCursor(1, $bytes);

            if ($code === 92) { // \
                $value .= $chunk;
                list (, $code) = $this->readChar(true);

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
                        $position = $this->position;
                        list ($hex) = $this->readChars(4, true);
                        if (!preg_match('/[0-9a-fA-F]{4}/', $hex)) {
                            throw new SyntaxError(
                                $this->source,
                                $position - 1,
                                'Invalid character escape sequence: \\u' . $hex
                            );
                        }
                        $code = hexdec($hex);
                        $this->assertValidStringCharacterCode($code, $position - 2);
                        $value .= Utils::chr($code);
                        break;
                    default:
                        throw new SyntaxError(
                            $this->source,
                            $this->position - 1,
                            'Invalid character escape sequence: \\' . Utils::chr($code)
                        );
                }
                $chunk = '';
            } else {
                $chunk .= $char;
            }

            list ($char, $code, $bytes) = $this->readChar();
        }

        if ($code !== 34) {
            throw new SyntaxError(
                $this->source,
                $this->position,
                'Unterminated string.'
            );
        }

        $value .= $chunk;

        // Skip trailing quote:
        $this->moveStringCursor(1, 1);

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
     * or commented character, then places cursor to the position of that character.
     */
    private function positionAfterWhitespace()
    {
        while ($this->position < $this->source->length) {
            list(, $code, $bytes) = $this->readChar();

            // Skip whitespace
            // tab | space | comma | BOM
            if ($code === 9 || $code === 32 || $code === 44 || $code === 0xFEFF) {
                $this->moveStringCursor(1, $bytes);
            } else if ($code === 10) { // new line
                $this->moveStringCursor(1, $bytes);
                $this->line++;
                $this->lineStart = $this->position;
            } else if ($code === 13) { // carriage return
                list(, $nextCode, $nextBytes) = $this->moveStringCursor(1, $bytes)->readChar();

                if ($nextCode === 10) { // lf after cr
                    $this->moveStringCursor(1, $nextBytes);
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
     * @param $line
     * @param $col
     * @param Token $prev
     * @return Token
     */
    private function readComment($line, $col, Token $prev)
    {
        $start = $this->position;
        $value = '';
        $bytes = 1;

        do {
            list ($char, $code, $bytes) = $this->moveStringCursor(1, $bytes)->readChar();
            $value .= $char;
        } while (
            $code &&
            // SourceCharacter but not LineTerminator
            ($code > 0x001F || $code === 0x0009)
        );

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
     * Reads next UTF8Character from the byte stream, starting from $byteStreamPosition.
     *
     * @param bool $advance
     * @param int $byteStreamPosition
     * @return array
     */
    private function readChar($advance = false, $byteStreamPosition = null)
    {
        if ($byteStreamPosition === null) {
            $byteStreamPosition = $this->byteStreamPosition;
        }

        $code = 0;
        $utf8char = '';
        $bytes = 0;
        $positionOffset = 0;

        if (isset($this->source->body[$byteStreamPosition])) {
            $ord = ord($this->source->body[$byteStreamPosition]);

            if ($ord < 128) {
                $bytes = 1;
            } else if ($ord < 224) {
                $bytes = 2;
            } elseif ($ord < 240) {
                $bytes = 3;
            } else {
                $bytes = 4;
            }

            $utf8char = '';
            for ($pos = $byteStreamPosition; $pos < $byteStreamPosition + $bytes; $pos++) {
                $utf8char .= $this->source->body[$pos];
            }
            $positionOffset = 1;
            $code = $bytes === 1 ? $ord : Utils::ord($utf8char);
        }

        if ($advance) {
            $this->moveStringCursor($positionOffset, $bytes);
        }

        return [$utf8char, $code, $bytes];
    }

    /**
     * Reads next $numberOfChars UTF8 characters from the byte stream, starting from $byteStreamPosition.
     *
     * @param $numberOfChars
     * @param bool $advance
     * @param null $byteStreamPosition
     * @return array
     */
    private function readChars($numberOfChars, $advance = false, $byteStreamPosition = null)
    {
        $result = '';
        $totalBytes = 0;
        $byteOffset = $byteStreamPosition ?: $this->byteStreamPosition;

        for ($i = 0; $i < $numberOfChars; $i++) {
            list ($char, $code, $bytes) = $this->readChar(false, $byteOffset);
            $totalBytes += $bytes;
            $byteOffset += $bytes;
            $result .= $char;
        }
        if ($advance) {
            $this->moveStringCursor($numberOfChars, $totalBytes);
        }
        return [$result, $totalBytes];
    }

    /**
     * Moves internal string cursor position
     *
     * @param $positionOffset
     * @param $byteStreamOffset
     * @return $this
     */
    private function moveStringCursor($positionOffset, $byteStreamOffset)
    {
        $this->position += $positionOffset;
        $this->byteStreamPosition += $byteStreamOffset;
        return $this;
    }
}
