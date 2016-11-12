<?php

namespace GraphQL\Language\AST;

class Argument extends Node
{
    protected $kind = NodeType::ARGUMENT;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Value
     */
    protected $value;

    public function __construct($name, $value, $loc = null)
    {
        $this->name = $name;
        $this->value = $value;
        $this->loc = $loc;
    }

    /**
     * @return Name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param Name $name
     *
     * @return Argument
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param Value $value
     *
     * @return Argument
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }
}
