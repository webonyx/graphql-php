<?php

namespace GraphQL\Language\AST;

class EnumValue extends Node implements Value
{
    protected $kind = NodeType::ENUM;

    /**
     * @var string
     */
    protected $value;

    /**
     * EnumValue constructor.
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
     * @return EnumValue
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
