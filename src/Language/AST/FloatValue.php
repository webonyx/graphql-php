<?php

namespace GraphQL\Language\AST;

class FloatValue extends Node implements Value
{
    protected $kind = NodeType::FLOAT;

    /**
     * @var string
     */
    protected $value;

    /**
     * FloatValue constructor.
     *
     * @param string $value
     * @param null   $loc
     */
    public function __construct($value, $loc = null)
    {
        $this->value = $value;
        $this->loc = $loc;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     *
     * @return FloatValue
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
