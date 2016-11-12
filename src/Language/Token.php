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
    protected $column;

    /**
     * @var string|null
     */
    protected $value;

    /**
     * Tokens exist as nodes in a double-linked-list amongst all tokens
     * including ignored tokens. <SOF> is always the first node and <EOF>
     * the last.
     *
     * @var Token
     */
    protected $prev;

    /**
     * @var Token
     */
    protected $next;

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
        $this->setKind($kind);
        $this->setStart((int) $start);
        $this->setEnd((int) $end);
        $this->setLine((int) $line);
        $this->setColumn((int) $column);
        $this->setPrev($previous);
        $this->setNext(null);
        $this->setValue($value);
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->getKind() . ($this->getValue() ? ' "' . $this->getValue()  . '"' : '');
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'kind' => $this->getKind(),
            'value' => $this->getValue(),
            'line' => $this->getLine(),
            'column' => $this->getColumn(),
            'start' => $this->getStart(),
            'end' => $this->getEnd()
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

    /**
     * @return int
     */
    public function getColumn()
    {
        return $this->column;
    }

    /**
     * @param int $column
     *
     * @return Token
     */
    public function setColumn($column)
    {
        $this->column = $column;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param null|string $value
     *
     * @return Token
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return Token
     */
    public function getPrev()
    {
        return $this->prev;
    }

    /**
     * @param Token $prev
     *
     * @return Token
     */
    public function setPrev($prev)
    {
        $this->prev = $prev;

        return $this;
    }

    /**
     * @return Token
     */
    public function getNext()
    {
        return $this->next;
    }

    /**
     * @param Token $next
     *
     * @return Token
     */
    public function setNext($next)
    {
        $this->next = $next;

        return $this;
    }
}
