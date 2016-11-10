<?php

namespace GraphQL\Language\AST;

class IntValue extends Node implements Value
{
    protected $kind = NodeType::INT;

    /**
     * @var string
     */
    protected $value;

    /**
     * IntValue constructor.
     *
     * @param array $value
     * @param null  $loc
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
     * @return IntValue
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
