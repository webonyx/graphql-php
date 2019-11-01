<?php

declare(strict_types=1);

namespace GraphQL\Language;

/**
 * Represents a range of characters represented by a lexical token
 * within a Source.
 */
class Token
{
    // Each kind of token.
    public const SOF          = '<SOF>';
    public const EOF          = '<EOF>';
    public const BANG         = '!';
    public const DOLLAR       = '$';
    public const AMP          = '&';
    public const PAREN_L      = '(';
    public const PAREN_R      = ')';
    public const SPREAD       = '...';
    public const COLON        = ':';
    public const EQUALS       = '=';
    public const AT           = '@';
    public const BRACKET_L    = '[';
    public const BRACKET_R    = ']';
    public const BRACE_L      = '{';
    public const PIPE         = '|';
    public const BRACE_R      = '}';
    public const NAME         = 'Name';
    public const INT          = 'Int';
    public const FLOAT        = 'Float';
    public const STRING       = 'String';
    public const BLOCK_STRING = 'BlockString';
    public const COMMENT      = 'Comment';

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

    /** @var string|null */
    public $value;

    /**
     * Tokens exist as nodes in a double-linked-list amongst all tokens
     * including ignored tokens. <SOF> is always the first node and <EOF>
     * the last.
     *
     * @var Token
     */
    public $prev;

    /** @var Token|null */
    public $next;

    /**
     * @param mixed $value
     */
    public function __construct(string $kind, int $start, int $end, int $line, int $column, ?Token $previous = null, $value = null)
    {
        $this->kind   = $kind;
        $this->start  = $start;
        $this->end    = $end;
        $this->line   = $line;
        $this->column = $column;
        $this->prev   = $previous;
        $this->next   = null;
        $this->value  = $value;
    }

    public function getDescription() : string
    {
        return $this->kind . ($this->value === null ? '' : ' "' . $this->value . '"');
    }

    /**
     * @return (string|int|null)[]
     */
    public function toArray() : array
    {
        return [
            'kind'   => $this->kind,
            'value'  => $this->value,
            'line'   => $this->line,
            'column' => $this->column,
        ];
    }
}
