<?php

namespace GraphQL\Language\AST;

class StringValue extends Node implements Value
{
    protected $kind = NodeType::STRING;

    /**
     * @var string
     */
    protected $value;

    /**
     * StringValue constructor.
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
     * @return StringValue
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
