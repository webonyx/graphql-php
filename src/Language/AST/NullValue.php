<?php

namespace GraphQL\Language\AST;

class NullValue extends Node implements Value
{
    public $kind = Node::NULL;

    /**
     * @var null
     */
    protected $value = null;

    /**
     * NullValue constructor.
     *
     * @param string $value
     * @param null   $loc
     */
    public function __construct($loc = null)
    {
        $this->loc = $loc;
    }

    /**
     * @return null
     */
    public function getValue()
    {
        return $this->value;
    }
}
