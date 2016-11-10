<?php

namespace GraphQL\Language\AST;

class ScalarTypeDefinition extends Node implements TypeDefinition
{
    /**
     * @var string
     */
    protected $kind = NodeType::SCALAR_TYPE_DEFINITION;

    /**
     * @var Name
     */
    protected $name;

    /**
     * @var Directive[]
     */
    protected $directives;

    /**
     * ScalarTypeDefinition constructor.
     *
     * @param Name  $name
     * @param array $directives
     * @param null  $loc
     */
    public function __construct(Name $name, array $directives = [], $loc = null)
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
     * @return ScalarTypeDefinition
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
     * @return ScalarTypeDefinition
     */
    public function setDirectives($directives)
    {
        $this->directives = $directives;

        return $this;
    }
}
