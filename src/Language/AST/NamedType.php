<?php

namespace GraphQL\Language\AST;

class NamedType extends Node implements Type
{
    protected $kind = NodeType::NAMED_TYPE;

    /**
     * @var Name
     */
    protected $name;

    public function __construct($name, $loc = null)
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
     * @return NamedType
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }
}
