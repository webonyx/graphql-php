<?php

namespace GraphQL\Language\AST;

class BooleanValue extends Node implements Value
{
    protected $kind = NodeType::BOOLEAN;

    /**
     * @var string
     */
    protected $value;

    /**
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
     * @return Name
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
