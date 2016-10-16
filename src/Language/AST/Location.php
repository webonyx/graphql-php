<?php
namespace GraphQL\Language\AST;

use GraphQL\Language\Source;
use GraphQL\Language\Token;

/**
 * Contains a range of UTF-8 character offsets and token references that
 * identify the region of the source from which the AST derived.
 */
class Location
{
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
     * The Token at which this Node begins.
     *
     * @var Token
     */
    public $startToken;

    /**
     * The Token at which this Node ends.
     *
     * @var Token
     */
    public $endToken;

    /**
     * The Source document the AST represents.
     *
     * @var Source|null
     */
    public $source;

    public function __construct(Token $startToken, Token $endToken, Source $source = null)
    {
        $this->startToken = $startToken;
        $this->endToken = $endToken;
        $this->start = $startToken->start;
        $this->end = $endToken->end;
        $this->source = $source;
    }
}
