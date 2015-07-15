<?php
namespace GraphQL\Language\AST;

use GraphQL\Language\Source;

class Location
{
    /**
     * @var int
     */
    public $start;

    /**
     * @var int
     */
    public $end;

    /**
     * @var Source|null
     */
    public $source;

    public function __construct($start, $end, Source $source = null)
    {
        $this->start = $start;
        $this->end = $end;
        $this->source = $source;
    }
}
