<?php

namespace GraphQL\Language\AST;

class Variable extends Node
{
    protected $kind = NodeType::VARIABLE;

    /**
     * @var Name
     */
    protected $name;

    /**
     * Variable constructor.
     *
     * @param Name $name
     * @param null $loc
     */
    public function __construct(Name $name, $loc = null)
    {
        $this->name = $name;
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
     * @return Variable
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
