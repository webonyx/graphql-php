<?php

namespace GraphQL\Language\AST;

class EnumValueDefinition extends Node
{
    /**
     * @var string
     */
    protected $kind = NodeType::ENUM_VALUE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * EnumValueDefinition constructor.
     *
     * @param Name  $name
     * @param array $directives
     * @param null  $loc
     */
    public function __construct(Name $name, array $directives, $loc = null)
    {
        $this->name = $name;
        $this->directives = $directives;
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
     * @return EnumValueDefinition
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Directive[]
     */
    public function getDirectives()
    {
        return $this->directives;
    }

    /**
     * @param Directive[] $directives
     *
     * @return EnumValueDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }
}
