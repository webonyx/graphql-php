<?php
namespace GraphQL\Language;

/**
 * Represents a range of characters represented by a lexical token
 * within a Source.
 */
class Token
{
    // Each kind of token.
    const SOF = '<SOF>';
    const EOF = '<EOF>';
    const BANG = '!';
    const DOLLAR = '$';
    const PAREN_L = '(';
    const PAREN_R = ')';
    const SPREAD = '...';
    const COLON = ':';
    const EQUALS = '=';
    const AT = '@';
    const BRACKET_L = '[';
    const BRACKET_R = ']';
    const BRACE_L = '{';
    const PIPE = '|';
    const BRACE_R = '}';
    const NAME = 'Name';
    const INT = 'Int';
    const FLOAT = 'Float';
    const STRING = 'String';
    const COMMENT = 'Comment';

    /**
     * @param $kind
     * @return mixed
     */
    public static function getKindDescription($kind)
    {
        trigger_error('Deprecated as of 16.10.2016 ($kind itself contains description string now)', E_USER_DEPRECATED);

        $description = [];
        $description[self::SOF] = '<SOF>';
        $description[self::EOF] = '<EOF>';
        $description[self::BANG] = '!';
        $description[self::DOLLAR] = '$';
        $description[self::PAREN_L] = '(';
        $description[self::PAREN_R] = ')';
        $description[self::SPREAD] = '...';
        $description[self::COLON] = ':';
        $description[self::EQUALS] = '=';
        $description[self::AT] = '@';
        $description[self::BRACKET_L] = '[';
        $description[self::BRACKET_R] = ']';
        $description[self::BRACE_L] = '{';
        $description[self::PIPE] = '|';
        $description[self::BRACE_R] = '}';
        $description[self::NAME] = 'Name';
        $description[self::INT] = 'Int';
        $description[self::FLOAT] = 'Float';
        $description[self::STRING] = 'String';
        $description[self::COMMENT] = 'Comment';

        return $description[$kind];
    }

    /**
     * The kind of Token (see one of constants above).
     *
     * @var string
     */
    public $kind;

    /**
     * The character offset at which this Node begins.
     *
     * @var int
     */
    public $start;

    /**
     * The character offset at which this Node ends.
     *
     * @var int
     */
    public $end;

    /**
     * The 1-indexed line number on which this Token appears.
     *
     * @var int
     */
    public $line;

    /**
     * The 1-indexed column number at which this Token begins.
     *
     * @var int
     */
    public $column;

    /**
     * @var string|null
     */
    public $value;

    /**
     * Tokens exist as nodes in a double-linked-list amongst all tokens
     * including ignored tokens. <SOF> is always the first node and <EOF>
     * the last.
     *
     * @var Token
     */
    public $prev;

    /**
     * @var Token
     */
    public $next;

    /**
     * Token constructor.
     * @param $kind
     * @param $start
     * @param $end
     * @param $line
     * @param $column
     * @param Token $previous
     * @param null $value
     */
    public function __construct($kind, $start, $end, $line, $column, Token $previous = null, $value = null)
    {
        $this->kind = $kind;
        $this->start = (int) $start;
        $this->end = (int) $end;
        $this->line = (int) $line;
        $this->column = (int) $column;
        $this->prev = $previous;
        $this->next = null;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->kind . ($this->value ? ' "' . $this->value  . '"' : '');
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'kind' => $this->kind,
            'value' => $this->value,
            'line' => $this->line,
            'column' => $this->column
        ];
    }
}
