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
     * The kind of Token (see one of constants above).
     *
     * @var string
     */
    protected $kind;

    /**
     * The character offset at which this Node begins.
     *
     * @var int
     */
    protected $start;

    /**
     * The character offset at which this Node ends.
     *
     * @var int
     */
    protected $end;

    /**
     * The 1-indexed line number on which this Token appears.
     *
     * @var int
     */
    protected $line;

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
            'kind' => $this->getKind(),
            'value' => $this->value,
            'line' => $this->line,
            'column' => $this->column,
            'start' => $this->start,
            'end' => $this->end
        ];
    }

    /**
     * @return string
     */
    public function getKind()
    {
        return $this->kind;
    }

    /**
     * @param string $kind
     *
     * @return Token
     */
    public function setKind($kind)
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * @return int
     */
    public function getStart()
    {
        return $this->start;
    }

    /**
     * @param int $start
     *
     * @return Token
     */
    public function setStart($start)
    {
        $this->start = $start;

        return $this;
    }

    /**
     * @return int
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @param int $end
     *
     * @return Token
     */
    public function setEnd($end)
    {
        $this->end = $end;

        return $this;
    }

    /**
     * @return int
     */
    public function getLine()
    {
        return $this->line;
    }

    /**
     * @param int $line
     *
     * @return Token
     */
    public function setLine($line)
    {
        $this->line = $line;

        return $this;
    }
}
